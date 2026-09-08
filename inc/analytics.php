<?php
/**
 * Sites -> Analytics.
 *
 * Charts clicks per Site Category over the last 30 days, built from the
 * per-day click log go.php has been writing to each site's
 * "_sites_clicks_log" post meta since that feature was added -- there is no
 * click history from before that point, so a freshly-updated site will show
 * an empty chart until new clicks start coming in.
 *
 * Rendered as a plain inline SVG rather than a JS charting library: this
 * theme bundles its own copies of every script it uses rather than pulling
 * from a CDN, and a stacked bar chart is simple enough to not need one.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class IO_Analytics {

	/** Admin page slug. */
	const SLUG = 'io-analytics';

	/** What we chart. */
	const POST_TYPE  = 'sites';
	const TAXONOMY   = 'favorites';
	const CLICK_LOG  = '_sites_clicks_log';

	/** Who may view the page. */
	const CAPABILITY = 'manage_options';

	/** How many days back the chart covers. */
	const RANGE_DAYS = 30;

	public static $hook = '';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
	}

	public static function menu() {
		self::$hook = add_submenu_page(
			'edit.php?post_type=' . self::POST_TYPE,
			__( 'Analytics', 'i_theme' ),
			__( 'Analytics', 'i_theme' ),
			self::CAPABILITY,
			self::SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * The last RANGE_DAYS calendar days (site local time), oldest first.
	 * @return string[] Y-m-d
	 */
	private static function date_range() {
		$today = current_time( 'Y-m-d' );
		$days  = array();
		for ( $i = self::RANGE_DAYS - 1; $i >= 0; $i-- ) {
			$days[] = date( 'Y-m-d', strtotime( $today . " -{$i} days" ) );
		}
		return $days;
	}

	/**
	 * Per-category, per-day click totals for the chart, each category's
	 * color matching the one already used for its heading/sidebar link
	 * (see io_cat_colors() in inc/inc.php) so the chart agrees with the
	 * rest of the theme, not a separately-invented palette.
	 *
	 * @return array{series: array, days: string[]}
	 */
	private static function aggregate() {
		$days       = self::date_range();
		$days_index = array_flip( $days );
		$series     = array();

		$site_ids = get_posts( array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => self::CLICK_LOG,
		) );

		foreach ( $site_ids as $site_id ) {
			$log = get_post_meta( $site_id, self::CLICK_LOG, true );
			if ( ! is_array( $log ) || empty( $log ) ) {
				continue;
			}

			// Sites take exactly one Site Category (see README's "single
			// leaf category" convention), so the first term is the only one.
			$terms = wp_get_post_terms( $site_id, self::TAXONOMY, array( 'fields' => 'all' ) );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}
			$term = $terms[0];

			if ( ! isset( $series[ $term->term_id ] ) ) {
				$colors = io_cat_colors( $term->term_id );
				$series[ $term->term_id ] = array(
					'name'  => $term->name,
					'color' => $colors['light'],
					'days'  => array_fill_keys( $days, 0 ),
					'total' => 0,
				);
			}

			foreach ( $log as $date => $count ) {
				if ( isset( $days_index[ $date ] ) ) {
					$series[ $term->term_id ]['days'][ $date ] += (int) $count;
					$series[ $term->term_id ]['total']         += (int) $count;
				}
			}
		}

		// A category with no clicks at all in range adds nothing visible to
		// a stacked chart, so it's dropped rather than cluttering the legend.
		$series = array_filter( $series, array( __CLASS__, 'has_clicks' ) );
		uasort( $series, array( __CLASS__, 'by_total_desc' ) );

		return array( 'series' => array_values( $series ), 'days' => $days );
	}

	private static function has_clicks( $s ) {
		return $s['total'] > 0;
	}

	private static function by_total_desc( $a, $b ) {
		return $b['total'] - $a['total'];
	}

	/**
	 * A stacked bar chart, one bar per day, one colored segment per
	 * category -- so both "how much" and "which category" read at a glance.
	 * Each segment carries an SVG <title> as a native, no-JS-needed tooltip.
	 */
	private static function render_chart( $series, $days ) {
		$width  = 900;
		$height = 260;
		$pad_l  = 36;
		$pad_b  = 24;
		$pad_t  = 10;
		$chart_w = $width - $pad_l - 10;
		$chart_h = $height - $pad_t - $pad_b;
		$n       = count( $days );
		$bar_gap = 2;
		$bar_w   = $n ? max( 2, ( $chart_w / $n ) - $bar_gap ) : 0;

		$day_totals = array_fill_keys( $days, 0 );
		foreach ( $series as $s ) {
			foreach ( $s['days'] as $d => $c ) {
				$day_totals[ $d ] += $c;
			}
		}
		$max = max( 1, max( $day_totals ) );

		ob_start();
		?>
		<svg viewBox="0 0 <?php echo (int) $width; ?> <?php echo (int) $height; ?>" width="100%" height="<?php echo (int) $height; ?>" role="img" aria-label="<?php esc_attr_e( 'Clicks per category over time', 'i_theme' ); ?>">
			<?php
			for ( $g = 0; $g <= 4; $g++ ) {
				$y   = $pad_t + $chart_h - ( $g / 4 ) * $chart_h;
				$val = round( ( $g / 4 ) * $max );
				echo '<line x1="' . esc_attr( $pad_l ) . '" y1="' . esc_attr( $y ) . '" x2="' . esc_attr( $width - 10 ) . '" y2="' . esc_attr( $y ) . '" stroke="currentColor" stroke-opacity="0.08"/>';
				echo '<text x="' . esc_attr( $pad_l - 6 ) . '" y="' . esc_attr( $y + 3 ) . '" text-anchor="end" font-size="10" fill="currentColor" fill-opacity="0.55">' . esc_html( $val ) . '</text>';
			}

			foreach ( $days as $i => $day ) {
				$x = $pad_l + $i * ( $bar_w + $bar_gap );
				$y_cursor = $pad_t + $chart_h;
				foreach ( $series as $s ) {
					$count = $s['days'][ $day ];
					if ( $count <= 0 ) {
						continue;
					}
					$seg_h     = ( $count / $max ) * $chart_h;
					$y_cursor -= $seg_h;
					printf(
						'<rect x="%s" y="%s" width="%s" height="%s" fill="%s"><title>%s</title></rect>',
						esc_attr( $x ),
						esc_attr( $y_cursor ),
						esc_attr( $bar_w ),
						esc_attr( $seg_h ),
						esc_attr( $s['color'] ),
						esc_html(
							sprintf(
								/* translators: 1: category name, 2: click count, 3: date */
								_n( '%1$s: %2$d click on %3$s', '%1$s: %2$d clicks on %3$s', $count, 'i_theme' ),
								$s['name'],
								$count,
								$day
							)
						)
					);
				}
				$label_every = max( 1, (int) floor( $n / 10 ) );
				if ( $n <= 15 || 0 === $i % $label_every || $i === $n - 1 ) {
					echo '<text x="' . esc_attr( $x + $bar_w / 2 ) . '" y="' . esc_attr( $height - 6 ) . '" text-anchor="middle" font-size="9" fill="currentColor" fill-opacity="0.55">' . esc_html( date( 'n/j', strtotime( $day ) ) ) . '</text>';
				}
			}
			?>
		</svg>
		<?php
		return ob_get_clean();
	}

	public static function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to view this page.', 'i_theme' ) );
		}

		$agg    = self::aggregate();
		$series = $agg['series'];
		$days   = $agg['days'];
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Analytics', 'i_theme' ); ?></h1>
			<p style="color:#646970">
				<?php
				printf(
					/* translators: %d: number of days */
					esc_html__( 'Outbound clicks per Site Category over the last %d days.', 'i_theme' ),
					(int) self::RANGE_DAYS
				);
				?>
			</p>

			<?php if ( ! io_get_option( 'is_go' ) ) : ?>
				<div class="notice notice-warning">
					<p>
						<?php
						esc_html_e(
							'Clicks are only counted when outbound links are routed through the internal redirect. Turn on "Internal redirect" under Theme Settings to start collecting data.',
							'i_theme'
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<?php if ( empty( $series ) ) : ?>
				<div class="notice notice-info">
					<p><?php esc_html_e( 'No click data in this range yet. This chart only covers clicks recorded since this feature was added, so a brand new install (or one that just turned on the internal redirect) will start empty.', 'i_theme' ); ?></p>
				</div>
			<?php else : ?>
				<div style="background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:16px;max-width:960px">
					<?php echo self::render_chart( $series, $days ); // phpcs:ignore -- own escaped markup ?>
				</div>

				<table class="widefat striped" style="max-width:480px;margin-top:16px">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Category', 'i_theme' ); ?></th>
							<th><?php esc_html_e( 'Clicks', 'i_theme' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $series as $s ) : ?>
							<tr>
								<td>
									<span style="display:inline-block;width:10px;height:10px;border-radius:2px;margin-right:6px;vertical-align:middle;background:<?php echo esc_attr( $s['color'] ); ?>"></span>
									<?php echo esc_html( $s['name'] ); ?>
								</td>
								<td><?php echo esc_html( $s['total'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
IO_Analytics::init();
