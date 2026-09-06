<?php
/**
 * Bookmark Import/Export.
 *
 * Imports a browser bookmarks HTML export (Chrome, Edge, and anything else
 * using the standard Netscape Bookmark File Format) into the "sites" post
 * type, auto-creating Site Categories from folders (capped at the theme's
 * own 2-level depth; anything nested deeper flattens into its depth-2
 * ancestor). A duplicate URL -- already in the library, or repeated inside
 * the file itself -- is skipped rather than imported again, using the same
 * URL normalization Link Health uses so the two features agree on what
 * counts as "the same link".
 *
 * A preview step reports what was found (and how many will be skipped)
 * before anything touches the database; only confirming actually imports.
 * Import itself runs as time-boxed batches with a progress bar, the same
 * pattern Link Health uses, so a large library never risks a PHP timeout.
 *
 * Also exports every Sites entry back out as a standard bookmarks HTML file,
 * grouped by its current Site Category, so it opens correctly in a browser's
 * "Import bookmarks" dialog and round-trips back into this importer too.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class IO_Bookmarks {

	/** Admin page slug. */
	const SLUG = 'io-bookmarks';

	/** Who may use this page. */
	const CAPABILITY = 'manage_options';

	/** What we import into / export from. */
	const POST_TYPE = 'sites';
	const LINK_META = '_sites_link';
	const TAXONOMY  = 'favorites';

	/**
	 * Where a pending preview is stashed between the upload and confirm steps.
	 *
	 * Deliberately a plain option, not a transient: set_transient()/get_transient()
	 * hand off entirely to any active persistent object cache (Redis/Memcached,
	 * common on managed hosts) and skip the database. If that cache does not
	 * reliably return what was just written on the very next request -- a
	 * different app-server node, a per-item size cap, a host dropin that treats
	 * the transient group as non-persistent -- the preview vanishes immediately
	 * and the user sees "expired" right after uploading. A plain option always
	 * round-trips through the database, matching how OPT_STATE below already
	 * behaves reliably.
	 */
	const OPT_PREVIEWS = 'io_bm_previews';

	/** Where the in-progress (or last completed) import's state lives. */
	const OPT_STATE = 'io_bm_import_state';

	/** Same batching budget Link Health uses, for the same reason. */
	const BATCH_BUDGET = 15;

	/** Largest upload accepted. Real bookmark exports are a few hundred KB at most. */
	const MAX_UPLOAD_BYTES = 20 * MB_IN_BYTES;

	public static $hook = '';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );

		add_action( 'admin_post_io_bm_upload',  array( __CLASS__, 'handle_upload' ) );
		add_action( 'admin_post_io_bm_confirm', array( __CLASS__, 'handle_confirm' ) );
		add_action( 'admin_post_io_bm_export',  array( __CLASS__, 'handle_export' ) );

		add_action( 'wp_ajax_io_bm_step', array( __CLASS__, 'ajax_step' ) );
	}

	public static function menu() {
		self::$hook = add_submenu_page(
			'edit.php?post_type=' . self::POST_TYPE,
			__( 'Import/Export Bookmarks', 'i_theme' ),
			__( 'Import/Export', 'i_theme' ),
			self::CAPABILITY,
			self::SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function page_url() {
		return admin_url( 'edit.php?post_type=' . self::POST_TYPE . '&page=' . self::SLUG );
	}

	/* ---------------------------------------------------------------------
	 * Parsing
	 *
	 * Pure and side-effect-free: no DB access, no term/post creation. Safe
	 * to run during a preview before the user has decided anything.
	 * ------------------------------------------------------------------ */

	/**
	 * Parse a bookmarks HTML export into a flat list.
	 *
	 * @return array [ ['title'=>, 'url'=>, 'path'=>string[] up to 2 entries], ... ]
	 */
	public static function parse_bookmarks( $html ) {
		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		// The leading XML declaration is a common trick to force UTF-8 decoding;
		// bookmark exports declare it via <META> only, which loadHTML() does not
		// reliably honor on its own for non-ASCII titles.
		$dom->loadHTML( '<?xml encoding="UTF-8">' . $html );
		libxml_clear_errors();
		libxml_use_internal_errors( false );

		$dl = $dom->getElementsByTagName( 'dl' )->item( 0 );
		$bookmarks = array();
		if ( $dl ) {
			$pending = null;
			self::walk_dom( $dl, array(), $bookmarks, $pending );
		}
		return $bookmarks;
	}

	/**
	 * Recursively walk the parsed DOM, tracking the folder-name chain.
	 *
	 * The real structure browsers write never explicitly closes <DT>, so the
	 * HTML parser nests each item's <DT> inside the previous one rather than
	 * making them siblings. <DT> itself carries no meaning -- it is flattened
	 * here by recursing into it with the SAME $path and the SAME $pending
	 * reference, so a folder name captured deep in a nested <DT> chain is
	 * still visible to the sibling <DL> that represents its contents.
	 *
	 * @param DOMNode     $node      current container being walked
	 * @param string[]    $path      folder-name chain so far (capped at 2)
	 * @param array       &$bookmarks accumulator
	 * @param string|null &$pending  folder name most recently seen via <h3>
	 */
	private static function walk_dom( DOMNode $node, array $path, array &$bookmarks, &$pending ) {
		foreach ( $node->childNodes as $child ) {
			if ( XML_ELEMENT_NODE !== $child->nodeType ) {
				continue;
			}
			switch ( $child->tagName ) {
				case 'h3':
					$pending = trim( $child->textContent );
					break;

				case 'a':
					$bookmarks[] = array(
						'title' => trim( $child->textContent ),
						'url'   => trim( $child->getAttribute( 'href' ) ),
						'path'  => $path,
					);
					$pending = null;
					break;

				case 'dl':
					if ( null !== $pending ) {
						$name     = ( '' !== $pending ) ? $pending : __( 'Untitled folder', 'i_theme' );
						$new_path = ( count( $path ) < 2 ) ? array_merge( $path, array( $name ) ) : $path;
					} else {
						$new_path = $path;
					}
					$pending       = null;
					$inner_pending = null;
					self::walk_dom( $child, $new_path, $bookmarks, $inner_pending );
					break;

				case 'dt':
					self::walk_dom( $child, $path, $bookmarks, $pending );
					break;
			}
		}
	}

	/* ---------------------------------------------------------------------
	 * Preview (still no DB writes -- only reads, to compare against what
	 * already exists)
	 * ------------------------------------------------------------------ */

	/**
	 * Every normalized URL already present in the library, as a fast lookup
	 * set. Used to decide what a preview -- and later the real import --
	 * would consider a duplicate.
	 */
	private static function existing_normalized_urls() {
		global $wpdb;
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT pm.meta_value FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = %s AND p.post_type = %s
				AND p.post_status IN ('publish','draft','pending','private','future')",
				self::LINK_META,
				self::POST_TYPE
			)
		);
		$set = array();
		foreach ( (array) $rows as $url ) {
			$norm = IO_Link_Health::normalize_url( $url );
			if ( '' !== $norm ) {
				$set[ $norm ] = true;
			}
		}
		return $set;
	}

	/**
	 * Classify a parsed bookmark list: how many are usable, how many are
	 * duplicates (of the existing library, or of an earlier entry in the
	 * SAME file -- first occurrence wins), and how many folders will become
	 * new categories. Returns the filtered list actually worth importing
	 * alongside the counts, so the confirm step does not have to redo this
	 * classification.
	 */
	public static function build_preview( array $parsed ) {
		$existing = self::existing_normalized_urls();

		$invalid   = 0;
		$duplicate = 0;
		$seen      = array();
		$to_import = array();
		$folders   = array();

		foreach ( $parsed as $bm ) {
			$url = trim( (string) $bm['url'] );
			if ( '' === $url || ! preg_match( '#^https?://#i', $url ) ) {
				$invalid++;
				continue;
			}
			$norm = IO_Link_Health::normalize_url( $url );
			if ( isset( $existing[ $norm ] ) || isset( $seen[ $norm ] ) ) {
				$duplicate++;
				continue;
			}
			$seen[ $norm ] = true;
			$to_import[]   = array(
				'title' => $bm['title'],
				'url'   => $url,
				'path'  => $bm['path'],
			);
			if ( ! empty( $bm['path'] ) ) {
				$folders[ implode( '/', $bm['path'] ) ] = true;
			}
		}

		return array(
			'total'        => count( $parsed ),
			'invalid'      => $invalid,
			'duplicate'    => $duplicate,
			'will_import'  => count( $to_import ),
			'folder_count' => count( $folders ),
			'to_import'    => $to_import,
		);
	}

	/**
	 * Stash a built preview under a fresh token, pruning any other entries
	 * that have already expired so an abandoned upload (started but never
	 * confirmed or re-visited) does not accumulate forever.
	 *
	 * @return string the token to hand back to the browser
	 */
	private static function save_pending_preview( array $preview ) {
		$all = get_option( self::OPT_PREVIEWS, array() );
		if ( ! is_array( $all ) ) {
			$all = array();
		}

		$now = time();
		foreach ( $all as $existing_token => $entry ) {
			if ( ! isset( $entry['expires'] ) || $entry['expires'] < $now ) {
				unset( $all[ $existing_token ] );
			}
		}

		$token = wp_generate_uuid4();
		$all[ $token ] = array(
			'data'    => $preview,
			'expires' => $now + HOUR_IN_SECONDS,
		);
		update_option( self::OPT_PREVIEWS, $all, false );
		return $token;
	}

	/** Retrieve a still-valid pending preview by token, or null if gone/expired. */
	private static function get_pending_preview( $token ) {
		if ( '' === (string) $token ) {
			return null;
		}
		$all = get_option( self::OPT_PREVIEWS, array() );
		if ( ! is_array( $all ) || ! isset( $all[ $token ] ) ) {
			return null;
		}
		$entry = $all[ $token ];
		if ( ! isset( $entry['expires'] ) || $entry['expires'] < time() ) {
			return null;
		}
		return isset( $entry['data'] ) ? $entry['data'] : null;
	}

	/** Remove one pending preview (after it's confirmed, or found expired). */
	private static function delete_pending_preview( $token ) {
		$all = get_option( self::OPT_PREVIEWS, array() );
		if ( is_array( $all ) && isset( $all[ $token ] ) ) {
			unset( $all[ $token ] );
			update_option( self::OPT_PREVIEWS, $all, false );
		}
	}

	/* ---------------------------------------------------------------------
	 * Import: category resolution + batched insert
	 * ------------------------------------------------------------------ */

	/**
	 * Find or create a Site Category, scoped to a specific parent so that
	 * two different folders that happen to share a name (e.g. "Misc" under
	 * two different top-level folders) are never merged into one category.
	 */
	private static function get_or_create_category( $name, $parent_id ) {
		$name = sanitize_text_field( $name );
		if ( '' === $name ) {
			$name = __( 'Untitled folder', 'i_theme' );
		}

		$existing = term_exists( $name, self::TAXONOMY, $parent_id );
		if ( $existing && ! is_wp_error( $existing ) ) {
			return (int) $existing['term_id'];
		}

		$created = wp_insert_term( $name, self::TAXONOMY, array( 'parent' => $parent_id ) );
		if ( is_wp_error( $created ) ) {
			// Most likely a duplicate-term race; check again before giving up.
			$existing = term_exists( $name, self::TAXONOMY, $parent_id );
			if ( $existing && ! is_wp_error( $existing ) ) {
				return (int) $existing['term_id'];
			}
			return 0;
		}
		return (int) $created['term_id'];
	}

	/**
	 * Resolve every bookmark's folder path to a category term ID, creating
	 * new terms as needed (reusing one lookup per distinct path so the same
	 * folder mentioned by many bookmarks is only created once), then store
	 * the resulting queue as the import state. This is the only part of
	 * import that is not time-boxed -- a bookmarks file typically has a few
	 * dozen distinct folders at most, so this finishes in well under a
	 * second even for a library of a thousand links.
	 */
	public static function start_import( array $to_import ) {
		$category_cache = array(); // "parentId::name" => term_id
		$queue = array();

		foreach ( $to_import as $bm ) {
			$parent_id = 0;
			foreach ( $bm['path'] as $name ) {
				$key = $parent_id . '::' . $name;
				if ( ! isset( $category_cache[ $key ] ) ) {
					$category_cache[ $key ] = self::get_or_create_category( $name, $parent_id );
				}
				$parent_id = $category_cache[ $key ];
			}
			$queue[] = array(
				'title'   => $bm['title'],
				'url'     => $bm['url'],
				'term_id' => $parent_id,
			);
		}

		$state = array(
			'queue'    => $queue,
			'total'    => count( $queue ),
			'imported' => 0,
			'started'  => time(),
			'finished' => 0,
			'running'  => true,
		);
		update_option( self::OPT_STATE, $state, false );
		return $state;
	}

	public static function import_state() {
		$defaults = array(
			'queue'    => array(),
			'total'    => 0,
			'imported' => 0,
			'started'  => 0,
			'finished' => 0,
			'running'  => false,
		);
		$saved = get_option( self::OPT_STATE, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
	}

	/** Insert queued bookmarks until the time budget is spent or the queue empties. */
	public static function process_batch( $budget = self::BATCH_BUDGET ) {
		$state = self::import_state();

		if ( empty( $state['queue'] ) ) {
			return self::finish_import( $state );
		}

		$start = microtime( true );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 );
		}

		while ( ! empty( $state['queue'] ) && ( microtime( true ) - $start ) < $budget ) {
			$item    = array_shift( $state['queue'] );
			$post_id = wp_insert_post( array(
				'post_type'   => self::POST_TYPE,
				'post_title'  => wp_strip_all_tags( $item['title'] ) ?: __( '(untitled)', 'i_theme' ),
				'post_status' => 'publish',
			), true );

			if ( ! is_wp_error( $post_id ) && $post_id ) {
				update_post_meta( $post_id, self::LINK_META, esc_url_raw( $item['url'] ) );
				if ( ! empty( $item['term_id'] ) ) {
					wp_set_object_terms( $post_id, array( (int) $item['term_id'] ), self::TAXONOMY, false );
				}
				$state['imported']++;
			}
		}

		if ( empty( $state['queue'] ) ) {
			return self::finish_import( $state );
		}

		update_option( self::OPT_STATE, $state, false );
		return $state;
	}

	private static function finish_import( $state ) {
		$state['queue']    = array();
		$state['running']  = false;
		$state['finished'] = time();
		update_option( self::OPT_STATE, $state, false );
		return $state;
	}

	/* ---------------------------------------------------------------------
	 * Upload -> preview -> confirm -> batched import (admin-post handlers)
	 * ------------------------------------------------------------------ */

	public static function handle_upload() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'i_theme' ) );
		}
		check_admin_referer( 'io_bm_upload' );

		if ( empty( $_FILES['io_bm_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['io_bm_file']['tmp_name'] ) ) {
			self::redirect_with_error( 'upload' );
		}
		if ( (int) $_FILES['io_bm_file']['size'] > self::MAX_UPLOAD_BYTES ) {
			self::redirect_with_error( 'size' );
		}
		if ( ! preg_match( '/\.html?$/i', $_FILES['io_bm_file']['name'] ) ) {
			self::redirect_with_error( 'type' );
		}

		$html = file_get_contents( $_FILES['io_bm_file']['tmp_name'] );
		if ( false === $html || '' === trim( $html ) ) {
			self::redirect_with_error( 'empty' );
		}

		$parsed = self::parse_bookmarks( $html );
		if ( empty( $parsed ) ) {
			self::redirect_with_error( 'none' );
		}

		$preview = self::build_preview( $parsed );
		$token   = self::save_pending_preview( $preview );

		wp_safe_redirect( add_query_arg( 'preview', $token, self::page_url() ) );
		exit;
	}

	private static function redirect_with_error( $code ) {
		wp_safe_redirect( add_query_arg( 'io_bm_error', $code, self::page_url() ) );
		exit;
	}

	public static function handle_confirm() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'i_theme' ) );
		}
		check_admin_referer( 'io_bm_confirm' );

		$token   = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		$preview = $token ? self::get_pending_preview( $token ) : null;

		if ( ! $preview || empty( $preview['to_import'] ) ) {
			self::redirect_with_error( 'expired' );
		}

		self::delete_pending_preview( $token );
		self::start_import( $preview['to_import'] );

		wp_safe_redirect( self::page_url() );
		exit;
	}

	private static function guard_ajax() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'i_theme' ) ), 403 );
		}
		check_ajax_referer( 'io_bm_ajax', 'nonce' );
	}

	public static function ajax_step() {
		self::guard_ajax();
		$state = self::process_batch();
		wp_send_json_success( array(
			'total'     => (int) $state['total'],
			'imported'  => (int) $state['imported'],
			'remaining' => count( $state['queue'] ),
		) );
	}

	/* ---------------------------------------------------------------------
	 * Export
	 * ------------------------------------------------------------------ */

	public static function handle_export() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'i_theme' ) );
		}
		check_admin_referer( 'io_bm_export' );

		$html = self::build_export_html();

		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="webstack-bookmarks-' . gmdate( 'Y-m-d' ) . '.html"' );
		header( 'Content-Length: ' . strlen( $html ) );
		echo $html; // phpcs-ignore-ok: pre-escaped export markup, not admin page output
		exit;
	}

	public static function build_export_html() {
		$out  = "<!DOCTYPE NETSCAPE-Bookmark-file-1>\n";
		$out .= "<!-- This is an automatically generated file.\n     It will be read and overwritten.\n     DO NOT EDIT! -->\n";
		$out .= "<META HTTP-EQUIV=\"Content-Type\" CONTENT=\"text/html; charset=UTF-8\">\n";
		$out .= "<TITLE>Bookmarks</TITLE>\n<H1>Bookmarks</H1>\n<DL><p>\n";

		$top = get_terms( array( 'taxonomy' => self::TAXONOMY, 'hide_empty' => false, 'parent' => 0 ) );
		if ( ! is_wp_error( $top ) ) {
			foreach ( $top as $term ) {
				$out .= self::export_category_html( $term, 1 );
			}
		}
		$out .= self::export_uncategorized_html();
		$out .= "</DL><p>\n";
		return $out;
	}

	private static function export_category_html( $term, $depth ) {
		$indent = str_repeat( '    ', $depth );
		$out    = $indent . '<DT><H3 ADD_DATE="' . time() . '">' . esc_html( $term->name ) . "</H3>\n";
		$out   .= $indent . "<DL><p>\n";
		$out   .= self::export_bookmarks_html( $term->term_id, $indent . '    ' );

		$children = get_terms( array( 'taxonomy' => self::TAXONOMY, 'hide_empty' => false, 'parent' => $term->term_id ) );
		if ( ! is_wp_error( $children ) ) {
			foreach ( $children as $child ) {
				$out .= self::export_category_html( $child, $depth + 1 );
			}
		}

		$out .= $indent . "</DL><p>\n";
		return $out;
	}

	private static function export_bookmarks_html( $term_id, $indent ) {
		$posts = get_posts( array(
			'post_type'      => self::POST_TYPE,
			'posts_per_page' => -1,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'tax_query'      => array( array( 'taxonomy' => self::TAXONOMY, 'field' => 'term_id', 'terms' => $term_id ) ),
		) );
		$out = '';
		foreach ( $posts as $p ) {
			$url = get_post_meta( $p->ID, self::LINK_META, true );
			if ( ! $url ) {
				continue;
			}
			$date = $p->post_date_gmt ? mysql2date( 'U', $p->post_date_gmt ) : time();
			$out .= $indent . '<DT><A HREF="' . esc_attr( $url ) . '" ADD_DATE="' . (int) $date . '">' . esc_html( get_the_title( $p ) ) . "</A>\n";
		}
		return $out;
	}

	private static function export_uncategorized_html() {
		$posts = get_posts( array(
			'post_type'      => self::POST_TYPE,
			'posts_per_page' => -1,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'tax_query'      => array( array( 'taxonomy' => self::TAXONOMY, 'operator' => 'NOT EXISTS' ) ),
		) );
		$out = '';
		foreach ( $posts as $p ) {
			$url = get_post_meta( $p->ID, self::LINK_META, true );
			if ( ! $url ) {
				continue;
			}
			$date = $p->post_date_gmt ? mysql2date( 'U', $p->post_date_gmt ) : time();
			$out .= '    <DT><A HREF="' . esc_attr( $url ) . '" ADD_DATE="' . (int) $date . '">' . esc_html( get_the_title( $p ) ) . "</A>\n";
		}
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Admin page
	 * ------------------------------------------------------------------ */

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
			'nonce'   => wp_create_nonce( 'io_bm_ajax' ),
		);
		return 'var ioBM = ' . wp_json_encode( $data ) . ';' . <<<'JS'
jQuery(function($){
	var $bar = $('#io-bm-bar');
	if (!$bar.length) { return; }

	function step(){
		$.post(ioBM.ajaxurl, {action: 'io_bm_step', nonce: ioBM.nonce}).done(function(res){
			if (!res || !res.success) { return; }
			var s = res.data;
			var pct = s.total ? Math.round((s.imported / s.total) * 100) : 100;
			$('#io-bm-fill').css('width', pct + '%');
			$('#io-bm-progress-text').text(s.imported + ' / ' + s.total);
			if (s.remaining > 0) { step(); }
			else { window.location.reload(); }
		});
	}
	step();
});
JS;
	}

	public static function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to view this page.', 'i_theme' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import/Export Bookmarks', 'i_theme' ); ?></h1>
			<?php
			if ( isset( $_GET['io_bm_error'] ) ) {
				self::render_error_notice( sanitize_key( wp_unslash( $_GET['io_bm_error'] ) ) );
			}

			$state = self::import_state();

			if ( $state['running'] ) {
				self::render_importing( $state );
			} else {
				if ( $state['finished'] ) {
					self::render_summary( $state );
				}

				$rendered_preview = false;
				if ( isset( $_GET['preview'] ) ) {
					$token   = sanitize_text_field( wp_unslash( $_GET['preview'] ) );
					$preview = self::get_pending_preview( $token );
					if ( $preview ) {
						self::render_preview( $token, $preview );
						$rendered_preview = true;
					} else {
						echo '<div class="notice notice-warning"><p>' . esc_html__( 'That preview has expired. Please upload the file again.', 'i_theme' ) . '</p></div>';
					}
				}

				if ( ! $rendered_preview ) {
					self::render_upload_form();
					self::render_export_section();
				}
			}
			?>
		</div>
		<?php
	}

	private static function render_error_notice( $code ) {
		$messages = array(
			'upload'  => __( 'The file could not be uploaded. Please try again.', 'i_theme' ),
			'size'    => __( 'That file is larger than 20MB -- please check it is really a bookmarks export.', 'i_theme' ),
			'type'    => __( "Please upload an .html file exported from your browser's bookmarks manager.", 'i_theme' ),
			'empty'   => __( 'That file appears to be empty.', 'i_theme' ),
			'none'    => __( 'No bookmarks were found in that file.', 'i_theme' ),
			'expired' => __( 'That preview has expired. Please upload the file again.', 'i_theme' ),
		);
		$msg = isset( $messages[ $code ] ) ? $messages[ $code ] : __( 'Something went wrong with that file.', 'i_theme' );
		echo '<div class="notice notice-error"><p>' . esc_html( $msg ) . '</p></div>';
	}

	private static function render_upload_form() {
		?>
		<h2><?php esc_html_e( 'Import Bookmarks', 'i_theme' ); ?></h2>
		<p><?php esc_html_e( "Upload a bookmarks export from Chrome, Edge, or any browser using the standard bookmarks HTML format. You'll see exactly what was found before anything is imported.", 'i_theme' ); ?></p>
		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'io_bm_upload' ); ?>
			<input type="hidden" name="action" value="io_bm_upload">
			<input type="file" name="io_bm_file" accept=".html,.htm" required>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Analyze File', 'i_theme' ); ?></button>
		</form>
		<?php
	}

	private static function render_preview( $token, $preview ) {
		?>
		<h2><?php esc_html_e( 'Import Preview', 'i_theme' ); ?></h2>
		<table class="widefat" style="max-width:560px">
			<tbody>
				<tr><td><?php esc_html_e( 'Bookmarks found in file', 'i_theme' ); ?></td><td><strong><?php echo esc_html( $preview['total'] ); ?></strong></td></tr>
				<tr><td><?php esc_html_e( 'Not a usable web address (will be skipped)', 'i_theme' ); ?></td><td><?php echo esc_html( $preview['invalid'] ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Duplicates -- already in your library, or repeated in the file (will be skipped)', 'i_theme' ); ?></td><td><?php echo esc_html( $preview['duplicate'] ); ?></td></tr>
				<tr><td><?php esc_html_e( 'New Site Categories that will be created', 'i_theme' ); ?></td><td><?php echo esc_html( $preview['folder_count'] ); ?></td></tr>
				<tr style="font-weight:600"><td><?php esc_html_e( 'Will actually be imported', 'i_theme' ); ?></td><td><?php echo esc_html( $preview['will_import'] ); ?></td></tr>
			</tbody>
		</table>
		<p style="margin-top:16px">
			<?php if ( $preview['will_import'] > 0 ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
					<?php wp_nonce_field( 'io_bm_confirm' ); ?>
					<input type="hidden" name="action" value="io_bm_confirm">
					<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Confirm Import', 'i_theme' ); ?></button>
				</form>
			<?php else : ?>
				<em><?php esc_html_e( 'Nothing new to import -- every link in this file is already in your library or was not usable.', 'i_theme' ); ?></em>
			<?php endif; ?>
			<a class="button" href="<?php echo esc_url( self::page_url() ); ?>"><?php esc_html_e( 'Cancel', 'i_theme' ); ?></a>
		</p>
		<?php
	}

	private static function render_importing( $state ) {
		?>
		<h2><?php esc_html_e( 'Importing...', 'i_theme' ); ?></h2>
		<div id="io-bm-bar" style="background:#dcdcde;border-radius:3px;height:18px;max-width:520px;overflow:hidden">
			<div id="io-bm-fill" style="background:#2271b1;height:100%;width:0;transition:width .2s"></div>
		</div>
		<p>
			<span id="io-bm-progress-text"><?php echo esc_html( (int) $state['imported'] ); ?> / <?php echo esc_html( (int) $state['total'] ); ?></span>
			<?php esc_html_e( 'imported...', 'i_theme' ); ?>
		</p>
		<?php
	}

	private static function render_summary( $state ) {
		$skipped = max( 0, (int) $state['total'] - (int) $state['imported'] );
		?>
		<div class="notice notice-success">
			<p>
				<?php
				printf(
					/* translators: 1: number imported, 2: number skipped, 3: human-readable time since the import finished */
					esc_html__( 'Last import: %1$d added, %2$d skipped, %3$s ago.', 'i_theme' ),
					(int) $state['imported'],
					$skipped,
					esc_html( human_time_diff( $state['finished'], time() ) )
				);
				?>
			</p>
		</div>
		<?php
	}

	private static function render_export_section() {
		?>
		<h2><?php esc_html_e( 'Export Bookmarks', 'i_theme' ); ?></h2>
		<p><?php esc_html_e( "Download every Sites entry as a bookmarks HTML file, grouped by its Site Category. It opens directly in a browser's bookmark import dialog, and can be re-imported here too.", 'i_theme' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'io_bm_export' ); ?>
			<input type="hidden" name="action" value="io_bm_export">
			<button type="submit" class="button"><?php esc_html_e( 'Download Bookmarks HTML', 'i_theme' ); ?></button>
		</form>
		<?php
	}
}

IO_Bookmarks::init();
