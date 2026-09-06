<?php
/**
 * Link Health Check (detection and reporting).
 *
 * Checks the URL stored in the _sites_link meta of every "sites" entry and
 * records whether it is reachable. Results are shown under Sites > Link Health.
 *
 * This is the detection half of the feature. It never modifies or deletes any
 * content: scheduled runs only ever report. Bulk trashing is a separate step
 * that is deliberately not wired up yet.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class IO_Link_Health {

	/** Admin page slug. */
	const SLUG = 'io-link-health';

	/** Options. */
	const OPT_SETTINGS = 'io_hc_settings';
	const OPT_STATE    = 'io_hc_state';

	/** Cron hooks. */
	const CRON_SCAN = 'io_hc_scheduled_scan';
	const CRON_STEP = 'io_hc_continue_scan';

	/** Post meta written per link. */
	const META_STATUS = '_io_hc_status';
	const META_DETAIL = '_io_hc_detail';

	/** What we scan. */
	const POST_TYPE = 'sites';
	const LINK_META = '_sites_link';

	/** Who may view the page. */
	const CAPABILITY = 'manage_options';

	/**
	 * Seconds of wall clock a single batch may spend before yielding.
	 *
	 * Batches are time boxed rather than fixed count: a run of fast links gets
	 * through many, a run of slow ones does fewer, and neither trips
	 * max_execution_time. One in-flight request may overshoot by up to the
	 * configured timeout, so keep this comfortably under the PHP limit.
	 */
	const BATCH_BUDGET = 15;

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_save_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );

		add_action( 'wp_ajax_io_hc_start',   array( __CLASS__, 'ajax_start' ) );
		add_action( 'wp_ajax_io_hc_step',    array( __CLASS__, 'ajax_step' ) );
		add_action( 'wp_ajax_io_hc_recheck', array( __CLASS__, 'ajax_recheck' ) );

		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		add_action( self::CRON_SCAN, array( __CLASS__, 'run_scheduled' ) );
		add_action( self::CRON_STEP, array( __CLASS__, 'continue_scan' ) );

		// Do not leave orphaned cron events behind if the theme is switched away.
		add_action( 'switch_theme', array( __CLASS__, 'unschedule' ) );
	}

	/* ---------------------------------------------------------------------
	 * Settings and state
	 * ------------------------------------------------------------------ */

	public static function settings() {
		$defaults = array(
			'frequency' => 'manual', // manual | daily | weekly | monthly
			'timeout'   => 8,        // seconds per request
			'strikes'   => 2,        // consecutive failures before "broken"
		);
		$saved = get_option( self::OPT_SETTINGS, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
	}

	public static function state() {
		$defaults = array(
			'queue'     => array(),
			'total'     => 0,
			'processed' => 0,
			'started'   => 0,
			'finished'  => 0,
			'running'   => false,
			'counts'    => array( 'ok' => 0, 'broken' => 0, 'unverified' => 0 ),
		);
		$saved = get_option( self::OPT_STATE, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
	}

	private static function set_state( $state ) {
		// autoload "no": this can hold a queue and is only read on demand.
		update_option( self::OPT_STATE, $state, false );
	}

	public static function maybe_save_settings() {
		if ( ! isset( $_POST['io_hc_save'] ) ) {
			return;
		}
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		check_admin_referer( 'io_hc_settings' );

		$allowed   = array( 'manual', 'daily', 'weekly', 'monthly' );
		$frequency = isset( $_POST['io_hc_frequency'] ) ? sanitize_text_field( wp_unslash( $_POST['io_hc_frequency'] ) ) : 'manual';
		$timeout   = isset( $_POST['io_hc_timeout'] ) ? absint( $_POST['io_hc_timeout'] ) : 8;
		$strikes   = isset( $_POST['io_hc_strikes'] ) ? absint( $_POST['io_hc_strikes'] ) : 2;

		update_option( self::OPT_SETTINGS, array(
			'frequency' => in_array( $frequency, $allowed, true ) ? $frequency : 'manual',
			'timeout'   => max( 3, min( 30, $timeout ) ),
			'strikes'   => max( 1, min( 5, $strikes ) ),
		), false );

		self::reschedule();

		wp_safe_redirect( add_query_arg( 'io_hc_saved', '1', self::page_url() ) );
		exit;
	}

	public static function page_url() {
		return admin_url( 'edit.php?post_type=' . self::POST_TYPE . '&page=' . self::SLUG );
	}

	/* ---------------------------------------------------------------------
	 * Scheduling
	 * ------------------------------------------------------------------ */

	public static function cron_schedules( $schedules ) {
		if ( ! isset( $schedules['io_hc_weekly'] ) ) {
			$schedules['io_hc_weekly'] = array(
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( 'Once weekly (link health)', 'i_theme' ),
			);
		}
		if ( ! isset( $schedules['io_hc_monthly'] ) ) {
			$schedules['io_hc_monthly'] = array(
				'interval' => 30 * DAY_IN_SECONDS,
				'display'  => __( 'Once monthly (link health)', 'i_theme' ),
			);
		}
		return $schedules;
	}

	public static function reschedule() {
		self::unschedule();

		$settings = self::settings();
		$map      = array(
			'daily'   => 'daily',
			'weekly'  => 'io_hc_weekly',
			'monthly' => 'io_hc_monthly',
		);
		if ( isset( $map[ $settings['frequency'] ] ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, $map[ $settings['frequency'] ], self::CRON_SCAN );
		}
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::CRON_SCAN );
		wp_clear_scheduled_hook( self::CRON_STEP );
	}

	/** Entry point for a scheduled run. */
	public static function run_scheduled() {
		self::start_scan();
		self::continue_scan();
	}

	/** Process one batch, then re-queue itself until the queue is drained. */
	public static function continue_scan() {
		$state = self::process_batch();
		if ( ! empty( $state['queue'] ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::CRON_STEP );
		}
	}

	/* ---------------------------------------------------------------------
	 * Scanning
	 * ------------------------------------------------------------------ */

	/** Every sites entry that actually has a URL stored. */
	public static function collect_link_ids() {
		$q = new WP_Query( array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'meta_query'     => array(
				array(
					'key'     => self::LINK_META,
					'compare' => 'EXISTS',
				),
			),
		) );
		return array_map( 'intval', $q->posts );
	}

	/** Reset state and load the queue. */
	public static function start_scan() {
		$ids   = self::collect_link_ids();
		$state = array(
			'queue'     => $ids,
			'total'     => count( $ids ),
			'processed' => 0,
			'started'   => time(),
			'finished'  => 0,
			'running'   => true,
			'counts'    => array( 'ok' => 0, 'broken' => 0, 'unverified' => 0 ),
		);
		self::set_state( $state );
		return $state;
	}

	/**
	 * Check links until the time budget is spent or the queue empties.
	 */
	public static function process_batch( $budget = self::BATCH_BUDGET ) {
		$state = self::state();

		if ( empty( $state['queue'] ) ) {
			return self::finish( $state );
		}

		$settings = self::settings();
		$start    = microtime( true );

		// Best effort; many hosts disallow this and that is fine, the time box
		// is what actually keeps us inside the limit.
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 );
		}

		while ( ! empty( $state['queue'] ) && ( microtime( true ) - $start ) < $budget ) {
			$post_id = (int) array_shift( $state['queue'] );
			$url     = get_post_meta( $post_id, self::LINK_META, true );

			$result = self::check_url( $url, $settings['timeout'] );
			$status = self::record( $post_id, $result, $settings['strikes'] );

			if ( ! isset( $state['counts'][ $status ] ) ) {
				$state['counts'][ $status ] = 0;
			}
			$state['counts'][ $status ]++;
			$state['processed']++;
		}

		if ( empty( $state['queue'] ) ) {
			return self::finish( $state );
		}

		self::set_state( $state );
		return $state;
	}

	private static function finish( $state ) {
		$state['queue']    = array();
		$state['running']  = false;
		$state['finished'] = time();
		self::set_state( $state );
		return $state;
	}

	/**
	 * Ask a URL whether it is alive.
	 *
	 * Uses HEAD first so no page body is transferred. Servers that reject HEAD
	 * get one retry with a GET capped at 2KB, so we never pull a whole page.
	 *
	 * @return array status (ok|broken|unverified), code, message
	 */
	public static function check_url( $url, $timeout = 8 ) {
		$url = trim( (string) $url );

		if ( '' === $url ) {
			return array( 'status' => 'broken', 'code' => 0, 'message' => __( 'No URL is set for this entry', 'i_theme' ) );
		}
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			return array( 'status' => 'broken', 'code' => 0, 'message' => __( 'Not a valid http(s) URL', 'i_theme' ) );
		}

		$args = array(
			'timeout'     => $timeout,
			'redirection' => 5,
			'sslverify'   => true,
			'user-agent'  => 'Mozilla/5.0 (compatible; WebStackLinkHealth/1.0; +' . home_url( '/' ) . ')',
			'headers'     => array( 'Accept' => '*/*' ),
		);

		$res  = wp_remote_head( $url, $args );
		$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );

		if ( is_wp_error( $res ) || in_array( $code, array( 0, 405, 501 ), true ) ) {
			$get_args = $args;
			$get_args['limit_response_size'] = 2048;
			$retry = wp_remote_get( $url, $get_args );
			if ( ! is_wp_error( $retry ) ) {
				$res  = $retry;
				$code = (int) wp_remote_retrieve_response_code( $retry );
			} elseif ( is_wp_error( $res ) ) {
				$res = $retry;
			}
		}

		if ( is_wp_error( $res ) ) {
			$message = $res->get_error_message();
			// A timeout or a TLS problem is not proof the site is gone.
			if ( preg_match( '#timed out|timeout|SSL|certificate|handshake#i', $message ) ) {
				return array( 'status' => 'unverified', 'code' => 0, 'message' => $message );
			}
			return array( 'status' => 'broken', 'code' => 0, 'message' => $message );
		}

		if ( $code >= 200 && $code < 400 ) {
			return array( 'status' => 'ok', 'code' => $code, 'message' => '' );
		}

		// Bot protection and rate limiting look like failure but usually are not.
		if ( in_array( $code, array( 401, 403, 405, 406, 429 ), true ) ) {
			return array(
				'status'  => 'unverified',
				'code'    => $code,
				'message' => __( 'The site refused an automated request. It is probably online but blocks bots.', 'i_theme' ),
			);
		}

		return array(
			'status'  => 'broken',
			'code'    => $code,
			'message' => sprintf( __( 'Server responded %d', 'i_theme' ), $code ),
		);
	}

	/**
	 * Persist a result, applying the consecutive-failure rule.
	 *
	 * A link has to fail on $strikes consecutive checks before it is called
	 * broken, so a momentary outage does not condemn a live site.
	 */
	private static function record( $post_id, $result, $strikes ) {
		$previous = get_post_meta( $post_id, self::META_DETAIL, true );
		$fails    = ( is_array( $previous ) && isset( $previous['fails'] ) ) ? (int) $previous['fails'] : 0;

		if ( 'broken' === $result['status'] ) {
			$fails++;
			if ( $fails < $strikes ) {
				$result['status']  = 'unverified';
				$result['message'] = sprintf(
					/* translators: 1: failures so far, 2: failures required */
					__( 'Failed %1$d of %2$d checks. It will be reported as broken if it fails again.', 'i_theme' ),
					$fails,
					$strikes
				);
			}
		} else {
			$fails = 0;
		}

		update_post_meta( $post_id, self::META_STATUS, $result['status'] );
		update_post_meta( $post_id, self::META_DETAIL, array(
			'code'    => (int) $result['code'],
			'message' => (string) $result['message'],
			'checked' => time(),
			'fails'   => $fails,
		) );

		return $result['status'];
	}

	public static function count_by_status( $status ) {
		$q = new WP_Query( array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'   => self::META_STATUS,
					'value' => $status,
				),
			),
		) );
		return (int) $q->found_posts;
	}

	/* ---------------------------------------------------------------------
	 * AJAX
	 * ------------------------------------------------------------------ */

	private static function guard() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'i_theme' ) ), 403 );
		}
		check_ajax_referer( 'io_hc_ajax', 'nonce' );
	}

	public static function ajax_start() {
		self::guard();
		wp_send_json_success( self::public_state( self::start_scan() ) );
	}

	public static function ajax_step() {
		self::guard();
		wp_send_json_success( self::public_state( self::process_batch() ) );
	}

	public static function ajax_recheck() {
		self::guard();

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id || self::POST_TYPE !== get_post_type( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown entry.', 'i_theme' ) ), 400 );
		}

		$settings = self::settings();
		$url      = get_post_meta( $post_id, self::LINK_META, true );
		$result   = self::check_url( $url, $settings['timeout'] );

		// A manual recheck is a deliberate act, so let it settle the verdict
		// straight away rather than waiting for another strike.
		$status = self::record( $post_id, $result, 1 );

		wp_send_json_success( array(
			'status'  => $status,
			'label'   => self::status_label( $status ),
			'code'    => (int) $result['code'],
			'message' => (string) $result['message'],
		) );
	}

	private static function public_state( $state ) {
		return array(
			'total'     => (int) $state['total'],
			'processed' => (int) $state['processed'],
			'remaining' => count( $state['queue'] ),
			'running'   => (bool) $state['running'],
			'counts'    => $state['counts'],
		);
	}

	/* ---------------------------------------------------------------------
	 * Admin page
	 * ------------------------------------------------------------------ */

	public static $hook = '';

	public static function menu() {
		self::$hook = add_submenu_page(
			'edit.php?post_type=' . self::POST_TYPE,
			__( 'Link Health', 'i_theme' ),
			__( 'Link Health', 'i_theme' ),
			self::CAPABILITY,
			self::SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function enqueue( $hook ) {
		if ( self::$hook !== $hook ) {
			return;
		}
		wp_enqueue_script( 'jquery' );
		wp_add_inline_script( 'jquery', self::inline_js() );
	}

	private static function inline_js() {
		$data = array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'io_hc_ajax' ),
			'working' => __( 'Checking...', 'i_theme' ),
			'done'    => __( 'Finished. Reloading...', 'i_theme' ),
			'failed'  => __( 'The check could not be completed. Please try again.', 'i_theme' ),
			'recheck' => __( 'Recheck', 'i_theme' ),
		);

		return 'var ioHC = ' . wp_json_encode( $data ) . ';' . <<<'JS'
jQuery(function($){
	var $btn = $('#io-hc-run'), $bar = $('#io-hc-bar'), $fill = $('#io-hc-fill'), $txt = $('#io-hc-progress-text');

	function post(action, extra){
		return $.post(ioHC.ajaxurl, $.extend({action: action, nonce: ioHC.nonce}, extra || {}));
	}

	function render(s){
		var pct = s.total ? Math.round((s.processed / s.total) * 100) : 100;
		$fill.css('width', pct + '%');
		$txt.text(ioHC.working + ' ' + s.processed + ' / ' + s.total);
	}

	function step(){
		post('io_hc_step').done(function(res){
			if (!res || !res.success) { $txt.text(ioHC.failed); $btn.prop('disabled', false); return; }
			render(res.data);
			if (res.data.remaining > 0) { step(); }
			else { $txt.text(ioHC.done); window.location.reload(); }
		}).fail(function(){
			$txt.text(ioHC.failed);
			$btn.prop('disabled', false);
		});
	}

	$btn.on('click', function(e){
		e.preventDefault();
		$btn.prop('disabled', true);
		$bar.show();
		post('io_hc_start').done(function(res){
			if (!res || !res.success) { $txt.text(ioHC.failed); $btn.prop('disabled', false); return; }
			render(res.data);
			step();
		}).fail(function(){
			$txt.text(ioHC.failed);
			$btn.prop('disabled', false);
		});
	});

	$(document).on('click', '.io-hc-recheck', function(e){
		e.preventDefault();
		var $a = $(this), id = $a.data('id'), $row = $a.closest('tr');
		$a.text(ioHC.working);
		post('io_hc_recheck', {post_id: id}).done(function(res){
			if (res && res.success) {
				$row.find('.io-hc-status').html('<span class="io-hc-pill io-hc-' + res.data.status + '">' + res.data.label + '</span>');
				$row.find('.io-hc-reason').text(res.data.message || (res.data.code ? 'HTTP ' + res.data.code : ''));
			}
			$a.text(ioHC.recheck);
		}).fail(function(){ $a.text(ioHC.recheck); });
	});
});
JS;
	}

	public static function status_label( $status ) {
		switch ( $status ) {
			case 'ok':
				return __( 'OK', 'i_theme' );
			case 'broken':
				return __( 'Broken', 'i_theme' );
			case 'unverified':
				return __( 'Could not verify', 'i_theme' );
		}
		return __( 'Not checked', 'i_theme' );
	}

	public static function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to view this page.', 'i_theme' ) );
		}

		$settings = self::settings();
		$state    = self::state();
		$filter   = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'broken';
		if ( ! in_array( $filter, array( 'broken', 'unverified', 'ok', 'all' ), true ) ) {
			$filter = 'broken';
		}

		$counts = array(
			'broken'     => self::count_by_status( 'broken' ),
			'unverified' => self::count_by_status( 'unverified' ),
			'ok'         => self::count_by_status( 'ok' ),
		);
		$next = wp_next_scheduled( self::CRON_SCAN );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Link Health', 'i_theme' ); ?></h1>

			<?php if ( isset( $_GET['io_hc_saved'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'i_theme' ); ?></p></div>
			<?php endif; ?>

			<style>
				.io-hc-pill{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;line-height:18px;font-weight:600}
				.io-hc-ok{background:#e6f4ea;color:#137333}
				.io-hc-broken{background:#fce8e6;color:#c5221f}
				.io-hc-unverified{background:#fef7e0;color:#b06000}
				.io-hc-cards{display:flex;gap:12px;margin:16px 0;flex-wrap:wrap}
				.io-hc-card{background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:12px 18px;min-width:120px}
				.io-hc-card .n{font-size:22px;font-weight:600;display:block}
				.io-hc-url{max-width:340px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block}
				#io-hc-bar{display:none;background:#dcdcde;border-radius:3px;height:18px;margin:10px 0;max-width:520px;overflow:hidden}
				#io-hc-fill{background:#2271b1;height:100%;width:0;transition:width .2s}
			</style>

			<div class="io-hc-cards">
				<div class="io-hc-card"><span class="n"><?php echo esc_html( $counts['broken'] ); ?></span><?php esc_html_e( 'Broken', 'i_theme' ); ?></div>
				<div class="io-hc-card"><span class="n"><?php echo esc_html( $counts['unverified'] ); ?></span><?php esc_html_e( 'Could not verify', 'i_theme' ); ?></div>
				<div class="io-hc-card"><span class="n"><?php echo esc_html( $counts['ok'] ); ?></span><?php esc_html_e( 'OK', 'i_theme' ); ?></div>
			</div>

			<p>
				<button class="button button-primary" id="io-hc-run"><?php esc_html_e( 'Check all links now', 'i_theme' ); ?></button>
				<?php if ( $state['finished'] ) : ?>
					<span style="margin-left:10px;color:#646970">
						<?php
						printf(
							/* translators: %s: human readable time difference */
							esc_html__( 'Last run %s ago.', 'i_theme' ),
							esc_html( human_time_diff( $state['finished'], time() ) )
						);
						?>
					</span>
				<?php endif; ?>
				<?php if ( $next ) : ?>
					<span style="margin-left:10px;color:#646970">
						<?php
						printf(
							/* translators: %s: human readable time difference */
							esc_html__( 'Next scheduled run in %s.', 'i_theme' ),
							esc_html( human_time_diff( time(), $next ) )
						);
						?>
					</span>
				<?php endif; ?>
			</p>

			<div id="io-hc-bar"><div id="io-hc-fill"></div></div>
			<p id="io-hc-progress-text" style="color:#646970"></p>

			<h2><?php esc_html_e( 'Settings', 'i_theme' ); ?></h2>
			<form method="post" action="">
				<?php wp_nonce_field( 'io_hc_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="io_hc_frequency"><?php esc_html_e( 'Automatic checks', 'i_theme' ); ?></label></th>
						<td>
							<select name="io_hc_frequency" id="io_hc_frequency">
								<?php
								$choices = array(
									'manual'  => __( 'Manual only', 'i_theme' ),
									'daily'   => __( 'Daily', 'i_theme' ),
									'weekly'  => __( 'Weekly', 'i_theme' ),
									'monthly' => __( 'Monthly', 'i_theme' ),
								);
								foreach ( $choices as $value => $label ) {
									printf(
										'<option value="%s"%s>%s</option>',
										esc_attr( $value ),
										selected( $settings['frequency'], $value, false ),
										esc_html( $label )
									);
								}
								?>
							</select>
							<p class="description"><?php esc_html_e( 'Scheduled runs only report results. Nothing is ever deleted automatically.', 'i_theme' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="io_hc_timeout"><?php esc_html_e( 'Timeout', 'i_theme' ); ?></label></th>
						<td>
							<input type="number" name="io_hc_timeout" id="io_hc_timeout" min="3" max="30" value="<?php echo esc_attr( $settings['timeout'] ); ?>" class="small-text"> <?php esc_html_e( 'seconds', 'i_theme' ); ?>
							<p class="description"><?php esc_html_e( 'How long to wait for each site before giving up.', 'i_theme' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="io_hc_strikes"><?php esc_html_e( 'Failures before "broken"', 'i_theme' ); ?></label></th>
						<td>
							<input type="number" name="io_hc_strikes" id="io_hc_strikes" min="1" max="5" value="<?php echo esc_attr( $settings['strikes'] ); ?>" class="small-text">
							<p class="description"><?php esc_html_e( 'A link must fail this many checks in a row before it is reported as broken. Keeping this at 2 avoids condemning a site over a momentary outage.', 'i_theme' ); ?></p>
						</td>
					</tr>
				</table>
				<p class="submit"><button type="submit" name="io_hc_save" value="1" class="button"><?php esc_html_e( 'Save settings', 'i_theme' ); ?></button></p>
			</form>

			<h2><?php esc_html_e( 'Results', 'i_theme' ); ?></h2>
			<ul class="subsubsub">
				<?php
				$tabs = array(
					'broken'     => __( 'Broken', 'i_theme' ),
					'unverified' => __( 'Could not verify', 'i_theme' ),
					'ok'         => __( 'OK', 'i_theme' ),
					'all'        => __( 'All checked', 'i_theme' ),
				);
				$last = array_keys( $tabs );
				$last = end( $last );
				foreach ( $tabs as $key => $label ) {
					$count = isset( $counts[ $key ] ) ? ' (' . (int) $counts[ $key ] . ')' : '';
					printf(
						'<li><a href="%s"%s>%s%s</a>%s</li>',
						esc_url( add_query_arg( 'status', $key, self::page_url() ) ),
						$filter === $key ? ' class="current"' : '',
						esc_html( $label ),
						esc_html( $count ),
						$key === $last ? '' : ' |'
					);
				}
				?>
			</ul>
			<?php self::render_table( $filter ); ?>
		</div>
		<?php
	}

	private static function render_table( $filter ) {
		$paged = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );

		$meta_query = ( 'all' === $filter )
			? array( array( 'key' => self::META_STATUS, 'compare' => 'EXISTS' ) )
			: array( array( 'key' => self::META_STATUS, 'value' => $filter ) );

		$q = new WP_Query( array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page' => 50,
			'paged'          => $paged,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'meta_query'     => $meta_query,
		) );

		if ( ! $q->have_posts() ) {
			echo '<p>' . esc_html__( 'Nothing to show here yet. Run a check to populate this list.', 'i_theme' ) . '</p>';
			return;
		}
		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th scope="col" style="width:22%"><?php esc_html_e( 'Site', 'i_theme' ); ?></th>
					<th scope="col" style="width:26%"><?php esc_html_e( 'URL', 'i_theme' ); ?></th>
					<th scope="col" style="width:12%"><?php esc_html_e( 'Status', 'i_theme' ); ?></th>
					<th scope="col" style="width:24%"><?php esc_html_e( 'Reason', 'i_theme' ); ?></th>
					<th scope="col" style="width:16%"><?php esc_html_e( 'Last checked', 'i_theme' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php
			while ( $q->have_posts() ) :
				$q->the_post();
				$post_id = get_the_ID();
				$url     = (string) get_post_meta( $post_id, self::LINK_META, true );
				$status  = (string) get_post_meta( $post_id, self::META_STATUS, true );
				$detail  = get_post_meta( $post_id, self::META_DETAIL, true );
				$detail  = is_array( $detail ) ? $detail : array();

				$reason = '';
				if ( ! empty( $detail['message'] ) ) {
					$reason = $detail['message'];
				} elseif ( ! empty( $detail['code'] ) ) {
					$reason = 'HTTP ' . (int) $detail['code'];
				}
				?>
				<tr>
					<td>
						<strong><a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>"><?php echo esc_html( get_the_title() ? get_the_title() : __( '(no title)', 'i_theme' ) ); ?></a></strong>
						<div class="row-actions">
							<span class="edit"><a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>"><?php esc_html_e( 'Edit', 'i_theme' ); ?></a> | </span>
							<span><a href="#" class="io-hc-recheck" data-id="<?php echo esc_attr( $post_id ); ?>"><?php esc_html_e( 'Recheck', 'i_theme' ); ?></a></span>
						</div>
					</td>
					<td>
						<?php if ( $url ) : ?>
							<a class="io-hc-url" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $url ); ?></a>
						<?php else : ?>
							<em><?php esc_html_e( 'none', 'i_theme' ); ?></em>
						<?php endif; ?>
					</td>
					<td class="io-hc-status"><span class="io-hc-pill io-hc-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( self::status_label( $status ) ); ?></span></td>
					<td class="io-hc-reason"><?php echo esc_html( $reason ); ?></td>
					<td>
						<?php
						if ( ! empty( $detail['checked'] ) ) {
							printf(
								/* translators: %s: human readable time difference */
								esc_html__( '%s ago', 'i_theme' ),
								esc_html( human_time_diff( (int) $detail['checked'], time() ) )
							);
						} else {
							echo '&mdash;';
						}
						?>
					</td>
				</tr>
			<?php endwhile; ?>
			</tbody>
		</table>
		<?php
		wp_reset_postdata();

		if ( $q->max_num_pages > 1 ) {
			echo '<div class="tablenav"><div class="tablenav-pages">';
			echo wp_kses_post( paginate_links( array(
				'base'    => add_query_arg( 'paged', '%#%' ),
				'format'  => '',
				'current' => $paged,
				'total'   => $q->max_num_pages,
			) ) );
			echo '</div></div>';
		}
	}
}

IO_Link_Health::init();
