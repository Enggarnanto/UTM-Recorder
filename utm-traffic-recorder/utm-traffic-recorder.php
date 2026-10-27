<?php
/**
 * Plugin Name: UTM Traffic Recorder
 * Description: Records pageviews and UTM parameters, with a private WordPress admin report and CSV export.
 * Version: 2.6.11
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Plugin URI: https://www.mtalkblog.com
 * Author: Galih Enggarnanto
 * Author URI: https://www.mtalkblog.com
 * License: GPL-2.0-or-later
 * Text Domain: utm-traffic-recorder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'UTR_VERSION', '2.6.11' );
define( 'UTR_FILE', __FILE__ );
require_once __DIR__ . '/includes/wa-core.php';
require_once __DIR__ . '/includes/wa-admin.php';
require_once __DIR__ . '/includes/settings.php';

function utr_table_name() {
	global $wpdb;
	return $wpdb->prefix . 'utm_traffic_recorder';
}

function utr_activate() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table = utr_table_name();
	$charset = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		occurred_at datetime NOT NULL,
		visitor_id varchar(36) NOT NULL DEFAULT '',
		session_id varchar(36) NOT NULL,
		landing_path varchar(2048) NOT NULL DEFAULT '',
		page_path varchar(2048) NOT NULL DEFAULT '',
		referrer_host varchar(191) NOT NULL DEFAULT '',
		referrer_url varchar(2048) NOT NULL DEFAULT '',
		utm_source varchar(191) NOT NULL DEFAULT '',
		utm_medium varchar(191) NOT NULL DEFAULT '',
		utm_campaign varchar(191) NOT NULL DEFAULT '',
		utm_term varchar(191) NOT NULL DEFAULT '',
		utm_content varchar(191) NOT NULL DEFAULT '',
		utm_match_type varchar(191) NOT NULL DEFAULT '',
		PRIMARY KEY  (id),
		KEY occurred_at (occurred_at),
		KEY visitor_id (visitor_id),
		KEY session_id (session_id),
		KEY utm_source (utm_source),
		KEY utm_campaign (utm_campaign)
	) {$charset};";
	dbDelta( $sql );
	utr_install_wa_table();
	update_option( 'utr_schema_version', UTR_VERSION, false );
	if ( ! wp_next_scheduled( 'utr_daily_cleanup' ) ) {
		wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'utr_daily_cleanup' );
	}
}
register_activation_hook( __FILE__, 'utr_activate' );

function utr_upgrade_schema() {
	$installed = get_option( 'utr_schema_version' );
	if ( UTR_VERSION !== $installed ) {
		if ( $installed && version_compare( $installed, '2.1.0', '<' ) ) {
			$selected = get_option( 'utr_recorded_utm_fields', false );
			if ( is_array( $selected ) && ! in_array( 'utm_match_type', $selected, true ) ) {
				$selected[] = 'utm_match_type';
				update_option( 'utr_recorded_utm_fields', $selected, false );
			}
		}
		utr_activate();
	}
}
add_action( 'init', 'utr_upgrade_schema', 2 );

function utr_deactivate() {
	wp_clear_scheduled_hook( 'utr_daily_cleanup' );
}
register_deactivation_hook( __FILE__, 'utr_deactivate' );

function utr_cleanup() {
	global $wpdb;
	$table = utr_table_name();
	$cutoff = gmdate( 'Y-m-d H:i:s', time() - 90 * DAY_IN_SECONDS );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE occurred_at < %s", $cutoff ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	utr_cleanup_wa_clicks( $cutoff );
}
add_action( 'utr_daily_cleanup', 'utr_cleanup' );

function utr_enqueue_tracker() {
	if ( is_admin() || is_user_logged_in() || is_preview() ) {
		return;
	}
	wp_enqueue_script( 'utr-tracker', plugins_url( 'assets/tracker.js', __FILE__ ), array(), UTR_VERSION, true );
	wp_add_inline_script(
		'utr-tracker',
		'window.utrConfig = ' . wp_json_encode( array( 'endpoint' => esc_url_raw( rest_url( 'utm-recorder/v1/hit' ) ), 'fields' => utr_recorded_utm_fields() ) ) . ';',
		'before'
	);
}
add_action( 'wp_enqueue_scripts', 'utr_enqueue_tracker' );

function utr_print_utm_url_hider() {
	if ( is_admin() || ! get_option( 'utr_hide_utm_url', 0 ) ) {
		return;
	}
	// Print early even on themes that omit wp_footer(), and before delayed footer assets.
	$script = file_get_contents( plugin_dir_path( __FILE__ ) . 'assets/utm-hide.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( false !== $script ) {
		echo wp_get_inline_script_tag( $script, array( 'id' => 'utr-utm-hide' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted bundled script.
	}
}
add_action( 'wp_head', 'utr_print_utm_url_hider', 0 );

function utr_enqueue_utm_url_hider_fallback() {
	if ( is_admin() || ! get_option( 'utr_hide_utm_url', 0 ) ) {
		return;
	}
	// External fallback for sites whose Content Security Policy disallows inline scripts.
	wp_enqueue_script( 'utr-utm-hide', plugins_url( 'assets/utm-hide.js', __FILE__ ), array(), UTR_VERSION, false );
}
add_action( 'wp_enqueue_scripts', 'utr_enqueue_utm_url_hider_fallback' );

function utr_save_utm_url_setting() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Anda tidak memiliki izin untuk mengubah pengaturan ini.' );
	}
	check_admin_referer( 'utr_save_utm_url_setting' );
	update_option( 'utr_hide_utm_url', empty( $_POST['hide_utm_url'] ) ? 0 : 1 );
	wp_safe_redirect( admin_url( 'admin.php?page=utr-utm-manager&utr_notice=url_saved' ) );
	exit;
}
add_action( 'admin_post_utr_save_utm_url_setting', 'utr_save_utm_url_setting' );

function utr_register_routes() {
	register_rest_route(
		'utm-recorder/v1',
		'/hit',
		array(
			'methods'             => 'POST',
			'callback'            => 'utr_record_hit',
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'utr_register_routes' );

function utr_clean_value( $value, $limit = 191 ) {
	if ( ! is_string( $value ) ) {
		return '';
	}
	$value = sanitize_text_field( $value );
	return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $limit ) : wp_check_invalid_utf8( substr( $value, 0, $limit ), true );
}

function utr_clean_path( $value ) {
	if ( ! is_string( $value ) || 0 !== strpos( $value, '/' ) ) {
		return '/';
	}
	$path = wp_parse_url( $value, PHP_URL_PATH );
	return utr_clean_value( $path ?: '/', 2048 );
}

function utr_clean_uuid( $value ) {
	return is_string( $value ) && preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $value ) ? strtolower( $value ) : '';
}

function utr_clean_referrer_url( $value ) {
	if ( ! is_string( $value ) ) {
		return '';
	}
	$url = esc_url_raw( $value, array( 'http', 'https' ) );
	$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
	if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || ! wp_parse_url( $url, PHP_URL_HOST ) ) {
		return '';
	}
	return function_exists( 'mb_substr' ) ? mb_substr( $url, 0, 2048 ) : substr( $url, 0, 2048 );
}

function utr_record_hit( WP_REST_Request $request ) {
	global $wpdb;
	$origin = $request->get_header( 'origin' );
	$referer = $request->get_header( 'referer' );
	$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
	foreach ( array( $origin, $referer ) as $header ) {
		if ( $header && strtolower( (string) wp_parse_url( $header, PHP_URL_HOST ) ) !== strtolower( (string) $site_host ) ) {
			return new WP_Error( 'utr_invalid_origin', 'Invalid request origin.', array( 'status' => 403 ) );
		}
	}
	$session = utr_clean_uuid( $request->get_param( 'session_id' ) );
	if ( '' === $session ) {
		return new WP_Error( 'utr_invalid_session', 'Invalid session ID.', array( 'status' => 400 ) );
	}
	$visitor = utr_clean_uuid( $request->get_param( 'visitor_id' ) );
	if ( '' === $visitor ) {
		return new WP_Error( 'utr_invalid_visitor', 'Invalid browser ID.', array( 'status' => 400 ) );
	}
	$referrer_url = utr_clean_referrer_url( $request->get_param( 'referrer_url' ) );
	$data = array(
		'occurred_at'   => current_time( 'mysql', true ),
		'visitor_id'   => $visitor,
		'session_id'   => $session,
		'landing_path' => utr_clean_path( $request->get_param( 'landing_path' ) ),
		'page_path'    => utr_clean_path( $request->get_param( 'page_path' ) ),
		'referrer_host' => $referrer_url ? utr_clean_value( wp_parse_url( $referrer_url, PHP_URL_HOST ) ) : utr_clean_value( $request->get_param( 'referrer_host' ) ),
		'referrer_url'  => $referrer_url,
		'utm_source'   => utr_recorded_utm_value( $request, 'utm_source' ),
		'utm_medium'   => utr_recorded_utm_value( $request, 'utm_medium' ),
		'utm_campaign' => utr_recorded_utm_value( $request, 'utm_campaign' ),
		'utm_term'     => utr_recorded_utm_value( $request, 'utm_term' ),
		'utm_content'  => utr_recorded_utm_value( $request, 'utm_content' ),
		'utm_match_type' => utr_recorded_utm_value( $request, 'utm_match_type' ),
	);
	$inserted = $wpdb->insert( utr_table_name(), $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	if ( false === $inserted ) {
		return new WP_Error( 'utr_storage_failed', 'Could not record visit.', array( 'status' => 500 ) );
	}
	return new WP_REST_Response( array( 'recorded' => true ), 201 );
}

function utr_admin_menu() {
	add_menu_page( 'Traffic Recorder', 'Traffic Recorder', 'manage_options', 'utm-traffic-recorder', 'utr_render_admin', 'dashicons-chart-area', 60 );
	add_submenu_page( 'utm-traffic-recorder', 'Dashboard Trafik & UTM', 'Dashboard Trafik & UTM', 'manage_options', 'utm-traffic-recorder', 'utr_render_admin' );
	add_submenu_page( 'utm-traffic-recorder', 'Dashboard Chat WA', 'Dashboard Chat WA', 'manage_options', 'utr-wa-dashboard', 'utr_render_wa_dashboard' );
	add_submenu_page( 'utm-traffic-recorder', 'UTM Manager', 'UTM Manager', 'manage_options', 'utr-utm-manager', 'utr_render_utm_manager' );
	add_submenu_page( 'utm-traffic-recorder', 'CTA WA', 'CTA WA', 'manage_options', 'utr-whatsapp', 'utr_render_cta_manager' );
	add_submenu_page( 'utm-traffic-recorder', 'Tabel Price', 'Tabel Price', 'manage_options', 'utr-price-table', 'utr_render_price_table' );
	add_submenu_page( 'utm-traffic-recorder', 'Settings', 'Settings', 'manage_options', 'utr-settings', 'utr_render_feature_settings' );
}
add_action( 'admin_menu', 'utr_admin_menu' );

function utr_add_shortcode_metabox( $post_type, $post ) {
	if ( ! in_array( $post_type, array( 'post', 'page' ), true ) ) {
		return;
	}
	if ( function_exists( 'use_block_editor_for_post' ) && use_block_editor_for_post( $post ) ) {
		return;
	}
	add_meta_box( 'utr-wa-shortcodes', 'Shortcode WA', 'utr_render_shortcode_metabox', $post_type, 'side', 'default' );
}
add_action( 'add_meta_boxes', 'utr_add_shortcode_metabox', 10, 2 );

function utr_shortcode_items() {
	$items = array();
	foreach ( utr_wa_accounts() as $account ) {
		$id = isset( $account['id'] ) ? absint( $account['id'] ) : 0;
		if ( ! $id ) {
			continue;
		}
		$items[] = array(
			'name'      => isset( $account['name'] ) ? (string) $account['name'] : '',
			'shortcode' => '[utr_whatsapp id="' . $id . '"]',
		);
	}
	return $items;
}

function utr_render_shortcode_metabox() {
	$accounts = utr_wa_accounts();
	?>
	<div class="utr-shortcode-sidebar">
		<p>Salin lalu tempel di teks editor atau berikan kepada SEO Writer.</p>
		<?php if ( $accounts ) : ?>
			<?php foreach ( utr_shortcode_items() as $item ) : ?>
				<div class="utr-shortcode-item">
					<strong><?php echo esc_html( $item['name'] ); ?></strong>
					<code><?php echo esc_html( $item['shortcode'] ); ?></code>
					<button type="button" class="button button-small utr-copy-shortcode" data-shortcode="<?php echo esc_attr( $item['shortcode'] ); ?>">Salin</button>
				</div>
			<?php endforeach; ?>
			<p id="utr-copy-status" class="screen-reader-text" role="status" aria-live="polite"></p>
		<?php else : ?>
			<p>Belum ada akun WhatsApp. Tambahkan akun melalui Traffic Recorder → CTA WA.</p>
		<?php endif; ?>
	</div>
	<?php
}

function utr_render_price_table() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Tidak diizinkan.' );
	}
	?>
	<div class="wrap utr-admin">
		<header class="utr-header"><div><p class="utr-eyebrow">TRAFFIC RECORDER</p><h1>Tabel Price</h1><?php utr_render_header_version(); ?><p class="utr-subtitle">Soon</p></div></header><hr class="wp-header-end">
		<section class="utr-panel"><h2>Tabel Price</h2><p>Fitur tabel price segera hadir.</p></section>
	</div>
	<?php
}

function utr_render_brand_footer() {
	echo '<p class="utr-brand-footer">&copy; ' . esc_html( wp_date( 'Y' ) ) . ' <a href="https://www.mtalkblog.com/p/utm-traffic-recorder-dokumentasi.html" target="_blank" rel="noopener noreferrer">UTM Trafik Manager</a></p>';
}

function utr_render_header_version() {
	echo '<span class="utr-version-badge">v' . esc_html( UTR_VERSION ) . '</span>';
}

function utr_invalidate_wa_cache( $option ) {
	if ( ! in_array( $option, array( 'utr_whatsapp_settings', 'utr_wa_accounts', 'utr_wa_source_mode' ), true ) ) {
		return;
	}
	static $purged = false;
	if ( ! $purged ) {
		$purged = true;
		do_action( 'litespeed_purge_all', 'UTM Traffic Recorder: WhatsApp settings changed' );
	}
}
add_action( 'updated_option', 'utr_invalidate_wa_cache' );
add_action( 'added_option', 'utr_invalidate_wa_cache' );

function utr_refresh_version_cache() {
	if ( current_user_can( 'manage_options' ) && UTR_VERSION !== get_option( 'utr_loaded_version' ) ) {
		update_option( 'utr_loaded_version', UTR_VERSION, false );
		utr_invalidate_wa_cache( 'utr_whatsapp_settings' );
	}
}
add_action( 'admin_init', 'utr_refresh_version_cache' );

function utr_enqueue_admin_style( $hook ) {
	if ( false !== strpos( $hook, 'utm-traffic-recorder' ) || false !== strpos( $hook, 'utr-utm-manager' ) || false !== strpos( $hook, 'utr-whatsapp' ) || false !== strpos( $hook, 'utr-wa-dashboard' ) || false !== strpos( $hook, 'utr-settings' ) || false !== strpos( $hook, 'utr-price-table' ) ) {
		wp_enqueue_style( 'utr-brand-fonts', 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sora:wght@500;600;700;800&display=swap', array(), null );
		wp_enqueue_style( 'utr-admin', plugins_url( 'assets/admin.css', __FILE__ ), array( 'utr-brand-fonts' ), UTR_VERSION );
	}
	if ( false !== strpos( $hook, 'utr-whatsapp' ) ) {
		wp_enqueue_media();
		wp_enqueue_script( 'utr-wa-admin', plugins_url( 'assets/wa-admin.js', __FILE__ ), array( 'jquery', 'jquery-ui-sortable' ), UTR_VERSION, true );
		wp_add_inline_script( 'utr-wa-admin', 'window.utrWaAdmin = ' . wp_json_encode( array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'utr_wa_reorder' ) ) ) . ';', 'before' );
	}
	if ( false !== strpos( $hook, 'utm-traffic-recorder' ) || false !== strpos( $hook, 'utr-utm-manager' ) || false !== strpos( $hook, 'utr-whatsapp' ) || false !== strpos( $hook, 'utr-wa-dashboard' ) ) {
		wp_enqueue_script( 'utr-admin-tables', plugins_url( 'assets/admin-tables.js', __FILE__ ), array(), UTR_VERSION, true );
	}
}
add_action( 'admin_enqueue_scripts', 'utr_enqueue_admin_style' );

function utr_enqueue_shortcode_editor_assets( $hook ) {
	if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->post_type, array( 'post', 'page' ), true ) ) {
		return;
	}
	wp_enqueue_style( 'utr-shortcode-sidebar', plugins_url( 'assets/shortcode-sidebar.css', __FILE__ ), array(), UTR_VERSION );
	wp_enqueue_script( 'utr-shortcode-copy', plugins_url( 'assets/shortcode-copy.js', __FILE__ ), array(), UTR_VERSION, true );
	if ( $screen->is_block_editor() ) {
		wp_enqueue_script( 'utr-shortcode-block-sidebar', plugins_url( 'assets/shortcode-block-sidebar.js', __FILE__ ), array( 'wp-plugins', 'wp-editor', 'wp-edit-post', 'wp-element' ), UTR_VERSION, true );
		wp_add_inline_script( 'utr-shortcode-block-sidebar', 'window.utrShortcodeItems = ' . wp_json_encode( utr_shortcode_items() ) . ';', 'before' );
	}
}
add_action( 'admin_enqueue_scripts', 'utr_enqueue_shortcode_editor_assets' );

function utr_filters() {
	$today = wp_date( 'Y-m-d' );
	$default_from = wp_date( 'Y-m-d', time() - 29 * DAY_IN_SECONDS );
	$from = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : $default_from;
	$to = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : $today;
	$source = isset( $_GET['source'] ) ? utr_clean_value( wp_unslash( $_GET['source'] ) ) : '';
	$visitor_id = isset( $_GET['visitor_id'] ) ? utr_clean_uuid( wp_unslash( $_GET['visitor_id'] ) ) : '';
	foreach ( array( 'from', 'to' ) as $key ) {
		$value = 'from' === $key ? $from : $to;
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
		if ( ! $date || $date->format( 'Y-m-d' ) !== $value ) {
			if ( 'from' === $key ) {
				$from = $default_from;
			} else {
				$to = $today;
			}
		}
	}
	if ( $from > $to ) {
		list( $from, $to ) = array( $to, $from );
	}
	return array( $from, $to, $source, $visitor_id );
}

function utr_where( $filters ) {
	global $wpdb;
	list( $from, $to, $source, $visitor_id ) = $filters;
	$start = DateTimeImmutable::createFromFormat( '!Y-m-d', $from, wp_timezone() )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	$end = DateTimeImmutable::createFromFormat( '!Y-m-d', $to, wp_timezone() )->modify( '+1 day' )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	$where = $wpdb->prepare( 'WHERE occurred_at >= %s AND occurred_at < %s', $start, $end );
	if ( '' !== $source ) {
		$where .= $wpdb->prepare( ' AND utm_source = %s', $source );
	}
	if ( '' !== $visitor_id ) {
		$where .= $wpdb->prepare( ' AND visitor_id = %s', $visitor_id );
	}
	return $where;
}

function utr_quick_ranges() {
	$today = new DateTimeImmutable( 'today', wp_timezone() );
	return array(
		array( 'label' => 'Hari ini', 'days' => 1, 'from' => $today->format( 'Y-m-d' ) ),
		array( 'label' => '7 hari', 'days' => 7, 'from' => $today->modify( '-6 days' )->format( 'Y-m-d' ) ),
		array( 'label' => '12 hari', 'days' => 12, 'from' => $today->modify( '-11 days' )->format( 'Y-m-d' ) ),
		array( 'label' => '1 bulan', 'days' => 30, 'from' => $today->modify( '-29 days' )->format( 'Y-m-d' ) ),
		array( 'label' => '3 bulan', 'days' => 90, 'from' => $today->modify( '-89 days' )->format( 'Y-m-d' ) ),
	);
}

function utr_render_quick_ranges( $base, $filters, $extra_args = array() ) {
	$today = wp_date( 'Y-m-d' );
	echo '<div class="utr-quick-ranges" aria-label="Pilih periode cepat">';
	foreach ( utr_quick_ranges() as $range ) {
		$args = array_merge( $extra_args, array( 'from' => $range['from'], 'to' => $today, 'paged' => false ) );
		$url = add_query_arg( $args, $base );
		$active = $filters[0] === $range['from'] && $filters[1] === $today;
		echo '<a class="utr-range' . ( $active ? ' is-active' : '' ) . '" href="' . esc_url( $url ) . '" title="' . esc_attr( $range['days'] . ' hari termasuk hari ini' ) . '"' . ( $active ? ' aria-current="true"' : '' ) . '>' . esc_html( $range['label'] ) . '</a>';
	}
	echo '</div>';
}

function utr_query_rows( $filters, $limit = 50, $offset = 0 ) {
	global $wpdb;
	$table = utr_table_name();
	$where = utr_where( $filters );
	$limit = max( 1, absint( $limit ) );
	$offset = absint( $offset );
	return $wpdb->get_results( "SELECT * FROM {$table} {$where} ORDER BY occurred_at DESC, id DESC LIMIT {$limit} OFFSET {$offset}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

function utr_render_admin() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Anda tidak memiliki izin untuk melihat halaman ini.', 'utm-traffic-recorder' ) );
	}
	global $wpdb;
	$table = utr_table_name();
	$filters = utr_filters();
	$where = utr_where( $filters );
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$sessions = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT session_id) FROM {$table} {$where}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$visitors = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT NULLIF(visitor_id, '')) FROM {$table} {$where}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$tagged = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT session_id) FROM {$table} {$where} AND (utm_source <> '' OR utm_medium <> '' OR utm_campaign <> '' OR utm_term <> '' OR utm_content <> '' OR utm_match_type <> '')" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$click_table = utr_wa_click_table();
	$wa_clicks = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$click_table} {$where}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wa_click_sessions = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT NULLIF(session_id, '')) FROM {$click_table} {$where}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$recent_wa = $wpdb->get_results( "SELECT * FROM {$click_table} {$where} ORDER BY occurred_at DESC, id DESC LIMIT 10" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$top = $wpdb->get_results( "SELECT utm_source, COUNT(DISTINCT session_id) AS visits FROM {$table} {$where} AND utm_source <> '' GROUP BY utm_source ORDER BY visits DESC LIMIT 5" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$base = admin_url( 'admin.php?page=utm-traffic-recorder' );
	$manager_url = add_query_arg( array( 'from' => $filters[0], 'to' => $filters[1], 'source' => $filters[2] ), admin_url( 'admin.php?page=utr-utm-manager' ) );
	$attribution_rate = $sessions ? round( 100 * $tagged / $sessions ) : 0;
	$top_max = $top ? max( 1, (int) $top[0]->visits ) : 1;
	$today = wp_date( 'Y-m-d' );
	$quick_ranges = array(
		7  => wp_date( 'Y-m-d', time() - 6 * DAY_IN_SECONDS ),
		30 => wp_date( 'Y-m-d', time() - 29 * DAY_IN_SECONDS ),
		90 => wp_date( 'Y-m-d', time() - 89 * DAY_IN_SECONDS ),
	);
	$timezone = wp_timezone_string();
	?>
	<div class="wrap utr-admin">
		<header class="utr-header">
			<div>
				<p class="utr-eyebrow">ANALITIK WEBSITE</p>
				<h1>UTM Traffic Recorder</h1><?php utr_render_header_version(); ?>
				<p class="utr-subtitle">Pantau kunjungan dan lihat dari mana pengunjung datang.</p>
			</div>
			<a class="button button-primary utr-export" href="<?php echo esc_url( $manager_url ); ?>">Lihat detail UTM <span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span></a>
		</header><hr class="wp-header-end">

		<section class="utr-panel utr-filter-panel" aria-labelledby="utr-filter-heading">
			<div class="utr-panel-heading"><h2 id="utr-filter-heading">Periode laporan</h2><span>Zona waktu: <?php echo esc_html( $timezone ); ?></span></div>
			<div class="utr-quick-ranges" aria-label="Pilih periode cepat">
				<?php foreach ( $quick_ranges as $days => $start_date ) :
					$quick_url = add_query_arg( array( 'from' => $start_date, 'to' => $today, 'source' => $filters[2] ), $base );
					$is_active = $filters[0] === $start_date && $filters[1] === $today;
					?>
					<a class="utr-range<?php echo $is_active ? ' is-active' : ''; ?>" href="<?php echo esc_url( $quick_url ); ?>" <?php echo $is_active ? 'aria-current="true"' : ''; ?>><?php echo esc_html( $days ); ?> hari</a>
				<?php endforeach; ?>
			</div>
			<form class="utr-filters" method="get">
				<input type="hidden" name="page" value="utm-traffic-recorder">
				<label for="utr-from">Dari tanggal<input id="utr-from" type="date" name="from" value="<?php echo esc_attr( $filters[0] ); ?>" required></label>
				<label for="utr-to">Sampai tanggal<input id="utr-to" type="date" name="to" value="<?php echo esc_attr( $filters[1] ); ?>" required></label>
				<label for="utr-source">Sumber UTM<input id="utr-source" type="text" name="source" value="<?php echo esc_attr( $filters[2] ); ?>" placeholder="Semua sumber" maxlength="191"></label>
				<button class="button button-secondary" type="submit">Terapkan filter</button>
				<?php if ( '' !== $filters[2] ) : ?><a class="utr-clear" href="<?php echo esc_url( add_query_arg( array( 'from' => $filters[0], 'to' => $filters[1] ), $base ) ); ?>">Hapus sumber</a><?php endif; ?>
			</form>
		</section>

		<section class="utr-stats" aria-label="Ringkasan trafik">
			<div class="utr-stat"><span class="utr-stat-icon dashicons dashicons-id" aria-hidden="true"></span><span class="utr-stat-label">ID browser unik</span><strong><?php echo esc_html( number_format_i18n( $visitors ) ); ?></strong><small>Satu ID dapat memiliki beberapa sesi</small></div>
			<div class="utr-stat"><span class="utr-stat-icon dashicons dashicons-groups" aria-hidden="true"></span><span class="utr-stat-label">Total sesi unik</span><strong><?php echo esc_html( number_format_i18n( $sessions ) ); ?></strong><small>Setiap tab browser dihitung satu sesi</small></div>
			<div class="utr-stat"><span class="utr-stat-icon dashicons dashicons-visibility" aria-hidden="true"></span><span class="utr-stat-label">Tampilan halaman</span><strong><?php echo esc_html( number_format_i18n( $total ) ); ?></strong><small>Semua halaman yang dibuka</small></div>
			<div class="utr-stat"><span class="utr-stat-icon dashicons dashicons-tag" aria-hidden="true"></span><span class="utr-stat-label">Kunjungan dengan UTM</span><strong><?php echo esc_html( number_format_i18n( $tagged ) ); ?></strong><small><?php echo esc_html( $attribution_rate ); ?>% dari total kunjungan</small></div>
			<div class="utr-stat"><span class="utr-stat-icon dashicons dashicons-format-chat" aria-hidden="true"></span><span class="utr-stat-label">Klik Chat WA</span><strong><?php echo esc_html( number_format_i18n( $wa_clicks ) ); ?></strong><small><?php echo esc_html( number_format_i18n( $wa_click_sessions ) ); ?> sesi pengunjung; lihat UTM lengkap di <a href="<?php echo esc_url( admin_url( 'admin.php?page=utr-wa-dashboard' ) ); ?>">Dashboard Chat WA</a></small></div>
		</section>

		<section class="utr-panel utr-source-panel" aria-labelledby="utr-sources-heading">
			<div class="utr-panel-heading"><div><h2 id="utr-sources-heading">Sumber UTM teratas</h2><p>Sumber yang membawa kunjungan pada periode ini.</p></div></div>
			<?php if ( $top ) : ?>
				<ol class="utr-source-list">
					<?php foreach ( $top as $item ) :
						$source_url = add_query_arg( array( 'from' => $filters[0], 'to' => $filters[1], 'source' => $item->utm_source ), $base );
						$width = round( 100 * (int) $item->visits / $top_max );
						?>
						<li><a href="<?php echo esc_url( $source_url ); ?>"><span class="utr-source-name"><?php echo esc_html( $item->utm_source ); ?></span><span class="utr-source-track"><span style="width:<?php echo esc_attr( $width ); ?>%"></span></span><strong><?php echo esc_html( number_format_i18n( $item->visits ) ); ?></strong></a></li>
					<?php endforeach; ?>
				</ol>
			<?php else : ?>
				<p class="utr-empty">Belum ada sumber UTM untuk periode ini. Coba buka tautan kampanye yang memakai <code>utm_source</code>.</p>
			<?php endif; ?>
		</section>

		<section class="utr-panel utr-table-panel utr-recent-wa-panel" aria-labelledby="utr-recent-wa-heading">
			<div class="utr-panel-heading"><div><h2 id="utr-recent-wa-heading">Klik Chat WA terbaru</h2><p>Detail sumber dari setiap klik pada periode ini.</p></div><a href="<?php echo esc_url( admin_url( 'admin.php?page=utr-wa-dashboard' ) ); ?>">Lihat semua klik</a></div>
			<?php if ( $recent_wa ) : ?>
				<div class="utr-table-scroll"><table class="utr-table utr-detail-table utr-recent-click-table"><thead><tr><th>Waktu</th><th>Akun</th><th>Format sumber</th><th>ID sesi</th><th>Source</th><th>Medium</th><th>Campaign</th><th>Content</th><th>Term</th><th>Match Type</th></tr></thead><tbody>
				<?php foreach ( $recent_wa as $click ) : ?>
					<tr><td data-label="Waktu"><?php echo esc_html( wp_date( 'd M Y H:i', strtotime( $click->occurred_at . ' UTC' ) ) ); ?></td><td data-label="Akun"><?php echo esc_html( $click->account_name ?: $click->phone ); ?></td><td data-label="Format sumber"><?php echo esc_html( utr_wa_source_label( $click ) ); ?></td><td data-label="ID sesi"><?php echo '' !== $click->session_id ? '<code title="' . esc_attr( $click->session_id ) . '">' . esc_html( substr( $click->session_id, 0, 8 ) ) . '</code>' : '<span class="utr-muted">-</span>'; ?></td><td data-label="Source"><?php echo esc_html( $click->utm_source ); ?></td><td data-label="Medium"><?php echo esc_html( $click->utm_medium ); ?></td><td data-label="Campaign"><?php echo esc_html( $click->utm_campaign ); ?></td><td data-label="Content"><?php echo esc_html( $click->utm_content ); ?></td><td data-label="Term"><?php echo esc_html( $click->utm_term ); ?></td><td data-label="Match Type"><?php echo esc_html( $click->utm_match_type ); ?></td></tr>
				<?php endforeach; ?>
				</tbody></table></div>
			<?php else : ?><p class="utr-empty">Belum ada klik WhatsApp pada periode ini.</p><?php endif; ?>
		</section>

		<p class="utr-footnote">Data tersimpan selama 90 hari. Pengunjung yang login tidak dihitung. Buka UTM Manager untuk melihat parameter lengkap dan mengekspor CSV.</p>
		<?php utr_render_brand_footer(); ?>
	</div>
	<?php
}

function utr_render_utm_manager() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Anda tidak memiliki izin untuk melihat halaman ini.' );
	}
	global $wpdb;
	$filters = utr_filters();
	$where = utr_where( $filters );
	$table = utr_table_name();
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
	$page = min( $page, max( 1, (int) ceil( $total / 50 ) ) );
	$rows = utr_query_rows( $filters, 50, ( $page - 1 ) * 50 );
	$base = admin_url( 'admin.php?page=utr-utm-manager' );
	$filter_args = array( 'from' => $filters[0], 'to' => $filters[1], 'source' => $filters[2], 'visitor_id' => $filters[3] );
	$export = wp_nonce_url( add_query_arg( $filter_args, admin_url( 'admin-post.php?action=utr_export' ) ), 'utr_export' );
	$browser_limit = isset( $_GET['browser_limit'] ) ? min( 500, max( 1, absint( $_GET['browser_limit'] ) ) ) : 5;
	$filter_args['browser_limit'] = $browser_limit;
	$browser_rows = $wpdb->get_results( $wpdb->prepare( "SELECT visitor_id, COUNT(DISTINCT session_id) AS sessions, COUNT(*) AS pageviews, MAX(occurred_at) AS last_seen FROM {$table} {$where} AND visitor_id <> '' GROUP BY visitor_id ORDER BY last_seen DESC LIMIT %d", $browser_limit ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$top_fields = array(
		'utm_source'   => 'Top Source',
		'utm_medium'   => 'Top Medium',
		'utm_campaign' => 'Top Campaign',
		'utm_content'  => 'Top Content',
		'utm_term'     => 'Top Terms',
	);
	$top_values = array();
	foreach ( $top_fields as $field => $label ) {
		$top_values[ $field ] = $wpdb->get_results( "SELECT {$field} AS value, COUNT(DISTINCT session_id) AS sessions FROM {$table} {$where} AND {$field} <> '' GROUP BY {$field} ORDER BY sessions DESC, value ASC LIMIT 5" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Field names are fixed in $top_fields.
	}
	?>
	<div class="wrap utr-admin">
		<header class="utr-header"><div><p class="utr-eyebrow">DETAIL KUNJUNGAN</p><h1>UTM Manager</h1><?php utr_render_header_version(); ?><p class="utr-subtitle">Periksa semua parameter UTM yang tercatat pada setiap halaman.</p></div><a class="button button-primary utr-export" href="<?php echo esc_url( $export ); ?>"><span class="dashicons dashicons-download" aria-hidden="true"></span> Ekspor CSV</a></header><hr class="wp-header-end">
		<?php if ( isset( $_GET['utr_notice'] ) && 'url_saved' === sanitize_key( wp_unslash( $_GET['utr_notice'] ) ) ) : ?><div class="notice notice-success is-dismissible"><p>Pengaturan tampilan URL berhasil disimpan.</p></div><?php endif; ?>
		<section class="utr-panel utr-settings-panel utr-utm-url-panel" aria-labelledby="utr-url-heading">
			<h2 id="utr-url-heading">Tampilan URL pengunjung</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="utr_save_utm_url_setting">
				<?php wp_nonce_field( 'utr_save_utm_url_setting' ); ?>
				<label class="utr-switch-row"><input type="checkbox" name="hide_utm_url" value="1" <?php checked( get_option( 'utr_hide_utm_url', 0 ) ); ?>><span><strong>Sembunyikan parameter UTM dari address bar</strong><small>Parameter UTM yang didukung, termasuk utm_matchtype/utm_match_type, dibersihkan segera saat halaman dibuka. Pencatatan dan sumber pesan WhatsApp tetap memakai UTM yang masuk. Parameter URL lain tetap ada.</small></span></label>
				<button class="button button-primary" type="submit">Simpan pengaturan</button>
			</form>
			<p class="utr-utm-url-note">Tautan kampanye yang dibagikan masih memuat UTM agar sumber kunjungan dapat dikenali.</p>
		</section>
		<section class="utr-panel utr-filter-panel" aria-labelledby="utr-manager-filter-heading">
			<div class="utr-panel-heading"><h2 id="utr-manager-filter-heading">Filter data</h2><span>Zona waktu: <?php echo esc_html( wp_timezone_string() ); ?></span></div>
			<?php utr_render_quick_ranges( $base, $filters, array( 'source' => $filters[2], 'visitor_id' => $filters[3], 'browser_limit' => $browser_limit ) ); ?>
			<form class="utr-filters utr-manager-filters" method="get">
				<input type="hidden" name="page" value="utr-utm-manager">
				<input type="hidden" name="browser_limit" value="<?php echo esc_attr( $browser_limit ); ?>">
				<label for="utr-manager-from">Dari tanggal<input id="utr-manager-from" type="date" name="from" value="<?php echo esc_attr( $filters[0] ); ?>" required></label>
				<label for="utr-manager-to">Sampai tanggal<input id="utr-manager-to" type="date" name="to" value="<?php echo esc_attr( $filters[1] ); ?>" required></label>
				<label for="utr-manager-source">UTM source<input id="utr-manager-source" type="text" name="source" value="<?php echo esc_attr( $filters[2] ); ?>" placeholder="Semua sumber" maxlength="191"></label>
				<label for="utr-manager-visitor">ID browser<input id="utr-manager-visitor" type="text" name="visitor_id" value="<?php echo esc_attr( $filters[3] ); ?>" placeholder="Semua browser" maxlength="36"></label>
				<button class="button button-secondary" type="submit">Terapkan filter</button>
				<a class="utr-clear" href="<?php echo esc_url( $base ); ?>">Reset</a>
			</form>
		</section>
		<section class="utr-panel utr-table-panel" aria-labelledby="utr-browser-heading">
			<div class="utr-panel-heading"><div><h2 id="utr-browser-heading">ID browser &amp; sesi</h2><p><?php echo esc_html( number_format_i18n( $browser_limit ) ); ?> ID browser terbaru sesuai filter. Pilih ID untuk melihat semua kunjungan dan sesinya.</p></div><form class="utr-row-limit" method="get"><input type="hidden" name="page" value="utr-utm-manager"><input type="hidden" name="from" value="<?php echo esc_attr( $filters[0] ); ?>"><input type="hidden" name="to" value="<?php echo esc_attr( $filters[1] ); ?>"><input type="hidden" name="source" value="<?php echo esc_attr( $filters[2] ); ?>"><input type="hidden" name="visitor_id" value="<?php echo esc_attr( $filters[3] ); ?>"><label for="utr-browser-limit">Jumlah baris</label><input id="utr-browser-limit" type="number" name="browser_limit" min="1" max="500" step="1" value="<?php echo esc_attr( $browser_limit ); ?>" required><button class="button button-secondary" type="submit">Tampilkan</button></form></div>
			<?php if ( $browser_rows ) : ?><div class="utr-table-scroll"><table class="utr-table"><thead><tr><th>ID browser</th><th>Sesi unik</th><th>Tampilan halaman</th><th>Aktivitas terakhir</th></tr></thead><tbody>
				<?php foreach ( $browser_rows as $browser_row ) : ?>
					<tr><td data-label="ID browser"><a href="<?php echo esc_url( add_query_arg( array_merge( $filter_args, array( 'visitor_id' => $browser_row->visitor_id, 'paged' => false ) ), $base ) ); ?>"><code><?php echo esc_html( $browser_row->visitor_id ); ?></code></a></td><td data-label="Sesi unik"><?php echo esc_html( number_format_i18n( $browser_row->sessions ) ); ?></td><td data-label="Tampilan halaman"><?php echo esc_html( number_format_i18n( $browser_row->pageviews ) ); ?></td><td data-label="Aktivitas terakhir"><?php echo esc_html( wp_date( 'd M Y, H:i', strtotime( $browser_row->last_seen . ' UTC' ) ) ); ?></td></tr>
				<?php endforeach; ?>
			</tbody></table></div><?php else : ?><p class="utr-empty">Belum ada ID browser pada periode ini. Data lama sebelum pembaruan belum memiliki ID browser.</p><?php endif; ?>
		</section>
		<section class="utr-top-grid" aria-label="Peringkat parameter UTM">
			<?php foreach ( $top_fields as $field => $label ) : ?>
				<div class="utr-panel utr-top-card"><h2><?php echo esc_html( $label ); ?></h2><p>Sesi unik sesuai filter</p>
				<?php if ( $top_values[ $field ] ) : ?><ol>
					<?php foreach ( $top_values[ $field ] as $item ) : ?><li><span title="<?php echo esc_attr( $item->value ); ?>"><?php echo esc_html( $item->value ); ?></span><strong><?php echo esc_html( number_format_i18n( $item->sessions ) ); ?></strong></li><?php endforeach; ?>
				</ol><?php else : ?><p class="utr-empty">Belum ada data.</p><?php endif; ?>
				</div>
			<?php endforeach; ?>
		</section>
		<section class="utr-panel utr-table-panel" aria-labelledby="utr-manager-table-heading">
			<div class="utr-panel-heading"><div><h2 id="utr-manager-table-heading">Detail traffic &amp; UTM</h2><p><?php echo esc_html( number_format_i18n( $total ) ); ?> pageview sesuai filter. Klik judul kolom untuk mengurutkan baris pada halaman tabel ini.</p></div></div>
			<?php if ( $rows ) : ?>
				<div class="utr-table-scroll"><table class="utr-table utr-detail-table"><thead><tr><th scope="col">Waktu</th><th scope="col">Halaman</th><th scope="col">Source</th><th scope="col">Medium</th><th scope="col">Campaign</th><th scope="col">Term</th><th scope="col">Content</th><th scope="col">Match Type</th><th scope="col">URL perujuk</th><th scope="col">ID browser</th><th scope="col">Sesi</th></tr></thead><tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td data-label="Waktu"><time datetime="<?php echo esc_attr( gmdate( 'c', strtotime( $row->occurred_at . ' UTC' ) ) ); ?>"><?php echo esc_html( wp_date( 'd M Y, H:i', strtotime( $row->occurred_at . ' UTC' ) ) ); ?></time></td>
						<td data-label="Halaman"><strong class="utr-path" title="<?php echo esc_attr( $row->page_path ); ?>"><?php echo esc_html( $row->page_path ); ?></strong><span class="utr-muted">Halaman pertama sesi: <?php echo esc_html( $row->landing_path ); ?></span></td>
						<td data-label="Source"><?php echo '' !== $row->utm_source ? esc_html( $row->utm_source ) : '<span class="utr-muted">-</span>'; ?></td>
						<td data-label="Medium"><?php echo '' !== $row->utm_medium ? esc_html( $row->utm_medium ) : '<span class="utr-muted">-</span>'; ?></td>
						<td data-label="Campaign"><?php echo '' !== $row->utm_campaign ? esc_html( $row->utm_campaign ) : '<span class="utr-muted">-</span>'; ?></td>
						<td data-label="Term"><?php echo '' !== $row->utm_term ? esc_html( $row->utm_term ) : '<span class="utr-muted">-</span>'; ?></td>
						<td data-label="Content"><?php echo '' !== $row->utm_content ? esc_html( $row->utm_content ) : '<span class="utr-muted">-</span>'; ?></td>
						<td data-label="Match Type"><?php echo '' !== $row->utm_match_type ? esc_html( $row->utm_match_type ) : '<span class="utr-muted">-</span>'; ?></td>
						<td data-label="URL perujuk"><?php echo '' !== $row->referrer_url ? '<span class="utr-referrer-url">' . esc_html( $row->referrer_url ) . '</span>' : ( '' !== $row->referrer_host ? esc_html( $row->referrer_host ) : '<span class="utr-muted">-</span>' ); ?></td>
						<td data-label="ID browser"><?php echo '' !== $row->visitor_id ? '<code title="' . esc_attr( $row->visitor_id ) . '">' . esc_html( substr( $row->visitor_id, 0, 8 ) ) . '</code>' : '<span class="utr-muted">-</span>'; ?></td>
						<td data-label="Sesi"><code title="<?php echo esc_attr( $row->session_id ); ?>"><?php echo esc_html( substr( $row->session_id, 0, 8 ) ); ?></code></td>
					</tr>
				<?php endforeach; ?>
				</tbody></table></div>
				<?php if ( $total > 50 ) : ?><nav class="utr-pagination" aria-label="Halaman data UTM"><?php echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( array_merge( $filter_args, array( 'paged' => '%#%' ) ), $base ), 'current' => $page, 'total' => (int) ceil( $total / 50 ), 'prev_text' => '&laquo; Sebelumnya', 'next_text' => 'Berikutnya &raquo;' ) ) ); ?></nav><?php endif; ?>
			<?php else : ?><div class="utr-empty-state"><span class="dashicons dashicons-chart-area" aria-hidden="true"></span><h3>Belum ada data</h3><p>Coba ubah filter atau buka situs dengan tautan berparameter UTM.</p></div><?php endif; ?>
		</section>
		<p class="utr-footnote">UTM baru mengganti atribusi aktif di browser untuk halaman berikutnya, termasuk sesi berikutnya. Riwayat yang sudah tercatat tetap memakai UTM saat kunjungan terjadi. ID browser dan sesi baru tersedia mulai versi 1.9.0.</p>
		<?php utr_render_brand_footer(); ?>
	</div>
	<?php
}

function utr_whatsapp_settings() {
	$defaults = array(
		'enabled'       => 0,
		'enhance_links' => 1,
		'floating_enabled' => 1,
		'phone'         => '',
		'label'         => 'Chat WhatsApp',
		'message'       => 'Halo, saya ingin bertanya tentang produk Anda.',
		'greeting'      => 'Halo! Pilih admin yang ingin Anda hubungi.',
		'panel_title'   => 'Chat dengan kami',
		'floating_position' => 'right',
		'floating_bg_color' => '#14213d',
		'floating_text_color' => '#ffffff',
		'floating_accent_color' => '#b9f227',
		'floating_size' => 100,
		'source_label'  => 'Sumber',
		'source_case'   => 'original',
		'source_fields' => array( 'utm_source', 'session_id' ),
		'source_separator' => '+',
		'fallback_enabled' => 1,
		'fallback_label' => 'Sumber',
		'fallback_url_mode' => 'page',
		'fallback_custom_url' => '',
		'fallback_id_fields' => array(),
	);
	$stored = get_option( 'utr_whatsapp_settings', array() );
	return wp_parse_args( is_array( $stored ) ? $stored : array(), $defaults );
}

function utr_valid_whatsapp_phone( $phone ) {
	return is_string( $phone ) && 1 === preg_match( '/^[1-9][0-9]{6,14}$/', $phone );
}

function utr_save_whatsapp_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Anda tidak memiliki izin untuk mengubah pengaturan ini.', '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'utr_save_whatsapp' );
	$raw_phone = isset( $_POST['phone'] ) && is_string( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : '';
	$phone = preg_replace( '/[^0-9]/', '', $raw_phone );
	$enabled = ! empty( $_POST['enabled'] ) ? 1 : 0;
	$enhance_links = ! empty( $_POST['enhance_links'] ) ? 1 : 0;
	if ( ( $enabled || '' !== $phone ) && ! utr_valid_whatsapp_phone( $phone ) ) {
		wp_safe_redirect( admin_url( 'admin.php?page=utr-whatsapp&utr_notice=invalid_phone' ) );
		exit;
	}
	$raw_label = isset( $_POST['label'] ) && is_string( $_POST['label'] ) ? wp_unslash( $_POST['label'] ) : '';
	$raw_message = isset( $_POST['message'] ) && is_string( $_POST['message'] ) ? wp_unslash( $_POST['message'] ) : '';
	$label = utr_clean_value( $raw_label, 40 );
	$message = sanitize_textarea_field( $raw_message );
	$message = function_exists( 'mb_substr' ) ? mb_substr( $message, 0, 1000 ) : wp_check_invalid_utf8( substr( $message, 0, 1000 ), true );
	$settings = utr_whatsapp_settings();
	$settings = array_merge( $settings, array(
		'enabled'       => $enabled,
		'enhance_links' => $enhance_links,
		'phone'         => $phone,
		'label'         => '' !== $label ? $label : 'Chat WhatsApp',
		'message'       => $message,
	) );
	update_option( 'utr_whatsapp_settings', $settings, false );
	wp_safe_redirect( admin_url( 'admin.php?page=utr-whatsapp&utr_notice=saved' ) );
	exit;
}

function utr_render_whatsapp_admin() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Anda tidak memiliki izin untuk melihat halaman ini.' );
	}
	$settings = utr_whatsapp_settings();
	$notice = isset( $_GET['utr_notice'] ) && is_string( $_GET['utr_notice'] ) ? sanitize_key( wp_unslash( $_GET['utr_notice'] ) ) : '';
	?>
	<div class="wrap utr-admin">
		<header class="utr-header"><div><p class="utr-eyebrow">PENGATURAN TOMBOL</p><h1>CTA WhatsApp</h1><?php utr_render_header_version(); ?><p class="utr-subtitle">Tampilkan tombol chat dan sertakan sumber kunjungan dalam pesan.</p></div></header><hr class="wp-header-end">
		<?php if ( 'saved' === $notice ) : ?><div class="notice notice-success is-dismissible"><p>Pengaturan WhatsApp berhasil disimpan.</p></div><?php endif; ?>
		<?php if ( 'invalid_phone' === $notice ) : ?><div class="notice notice-error"><p>Masukkan nomor WhatsApp internasional yang valid, diawali kode negara (contoh format: 62 diikuti nomor Anda).</p></div><?php endif; ?>
		<div class="utr-settings-grid">
			<section class="utr-panel utr-settings-panel" aria-labelledby="utr-wa-settings-heading">
				<h2 id="utr-wa-settings-heading">Atur tombol WhatsApp</h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="utr_save_whatsapp">
					<?php wp_nonce_field( 'utr_save_whatsapp' ); ?>
					<label class="utr-switch-row"><input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>><span><strong>Aktifkan tombol WhatsApp</strong><small>Tombol mengambang akan tampil di halaman publik setelah nomor diisi.</small></span></label>
					<label class="utr-switch-row utr-switch-spaced"><input type="checkbox" name="enhance_links" value="1" <?php checked( ! empty( $settings['enhance_links'] ) ); ?>><span><strong>Tambahkan sumber ke tautan WhatsApp yang sudah ada</strong><small>Nomor tujuan dan pesan bawaan setiap tautan tetap dipakai. Berlaku untuk tautan api.whatsapp.com/send dan wa.me.</small></span></label>
					<label class="utr-field" for="utr-wa-phone">Nomor WhatsApp untuk tombol mengambang<input id="utr-wa-phone" name="phone" type="tel" inputmode="numeric" value="<?php echo esc_attr( $settings['phone'] ); ?>" placeholder="62XXXXXXXXXX" maxlength="20" autocomplete="tel"><small>Wajib jika tombol mengambang diaktifkan. Tautan WhatsApp yang sudah ada tetap memakai nomornya sendiri.</small></label>
					<label class="utr-field" for="utr-wa-label">Teks tombol<input id="utr-wa-label" name="label" type="text" value="<?php echo esc_attr( $settings['label'] ); ?>" maxlength="40" placeholder="Chat WhatsApp"></label>
					<label class="utr-field" for="utr-wa-message">Pesan WhatsApp default<textarea id="utr-wa-message" name="message" rows="6" maxlength="1000" placeholder="Halo, saya ingin bertanya..."><?php echo esc_textarea( $settings['message'] ); ?></textarea><small>Dipakai oleh tombol mengambang dan tautan WhatsApp yang belum memiliki pesan sendiri. Keterangan sumber ditambahkan setelah pesan.</small></label>
					<?php submit_button( 'Simpan pengaturan', 'primary', 'submit', false ); ?>
				</form>
			</section>
			<aside class="utr-panel utr-preview-panel" aria-labelledby="utr-wa-preview-heading">
				<h2 id="utr-wa-preview-heading">Contoh hasil pesan</h2>
				<p>Tombol mengambang memakai pesan default. Tautan yang sudah memiliki pesan akan mempertahankan pesannya. Keterangan sumber ditambahkan di akhir.</p>
				<div class="utr-message-example"><strong>Tanpa UTM</strong><pre><?php echo esc_html( $settings['message'] ); ?>

Sumber: https://example.com/halaman+a1b2c3d4</pre></div>
				<div class="utr-message-example"><strong>Dengan UTM</strong><pre><?php echo esc_html( $settings['message'] ); ?>

Sumber: google+a1b2c3d4</pre></div>
				<div class="utr-message-example"><strong>Tautan dengan pesan sendiri</strong><pre>Halo, saya ingin bertanya tentang program ini.

Sumber: google+a1b2c3d4</pre></div>
				<p class="utr-preview-note">Jika pengunjung mendapat UTM baru dalam tab yang sama, pesan memakai UTM terbaru. Tanpa UTM, pesan memakai URL halaman saat tombol ditekan.</p>
			</aside>
		</div>
	</div>
	<?php
}

function utr_enqueue_whatsapp_assets() {
	$settings = utr_whatsapp_settings();
	$accounts = utr_wa_accounts();
	$track_links = utr_external_wa_tracking_enabled();
	if ( ! $accounts && empty( $settings['enhance_links'] ) && ! $track_links ) {
		return;
	}
	if ( $accounts ) {
		wp_enqueue_style( 'utr-brand-fonts', 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sora:wght@500;600;700;800&display=swap', array(), null );
		wp_enqueue_style( 'utr-whatsapp', plugins_url( 'assets/whatsapp.css', __FILE__ ), array( 'utr-brand-fonts' ), UTR_VERSION );
	}
	$wa_dependencies = wp_script_is( 'utr-tracker', 'enqueued' ) ? array( 'utr-tracker' ) : array();
	wp_enqueue_script( 'utr-whatsapp', plugins_url( 'assets/whatsapp.js', __FILE__ ), $wa_dependencies, UTR_VERSION, true );
	$known_accounts = array();
	foreach ( $accounts as $account ) {
		if ( ! empty( $account['id'] ) && ! empty( $account['phone'] ) && utr_valid_whatsapp_phone( $account['phone'] ) ) {
			$known_accounts[] = array( 'id' => absint( $account['id'] ), 'phone' => $account['phone'], 'message' => isset( $account['message'] ) ? $account['message'] : '', 'message_custom' => isset( $account['message_custom'] ) ? $account['message_custom'] : '' );
		}
	}
	wp_add_inline_script( 'utr-whatsapp', 'window.utrWhatsappConfig = ' . wp_json_encode( array(
		'message'        => $settings['message'],
		'enhanceLinks'   => ! empty( $settings['enhance_links'] ),
		'trackLinks'     => $track_links,
		'fields'         => utr_recorded_utm_fields(),
		'sourceMode'     => utr_wa_source_mode(),
		'sourceLabel'    => $settings['source_label'],
		'sourceCase'     => $settings['source_case'],
		'sourceFields'   => $settings['source_fields'],
		'sourceSeparator' => $settings['source_separator'],
		'fallbackEnabled' => ! empty( $settings['fallback_enabled'] ),
		'fallbackLabel' => $settings['fallback_label'],
		'fallbackUrlMode' => $settings['fallback_url_mode'],
		'fallbackCustomUrl' => $settings['fallback_custom_url'],
		'fallbackIdFields' => $settings['fallback_id_fields'],
		'accounts'       => $known_accounts,
		'recordEndpoint' => is_user_logged_in() ? '' : esc_url_raw( rest_url( 'utm-recorder/v1/wa-click' ) ),
	), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';', 'before' );
}
add_action( 'wp_enqueue_scripts', 'utr_enqueue_whatsapp_assets' );

function utr_render_whatsapp_button() {
	$settings = utr_whatsapp_settings();
	if ( empty( $settings['enabled'] ) || ! utr_valid_whatsapp_phone( $settings['phone'] ) ) {
		return;
	}
	?>
	<a id="utr-whatsapp-cta" class="utr-whatsapp-cta" href="<?php echo esc_url( 'https://wa.me/' . $settings['phone'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( $settings['label'] ); ?>">
		<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 11.5a8 8 0 0 1-8 8 8.8 8.8 0 0 1-3.4-.7L4 20l1.3-4.2A8 8 0 1 1 20 11.5Z"/><path d="M9 9.5c.6 2 2 3.4 4 4l1.3-1 1.7 1.1c-.3 1.5-1.2 2.2-2.4 2.1-3.5-.3-6-2.8-6.3-6.3-.1-1.2.6-2.1 2.1-2.4l1.1 1.7L9 9.5Z"/></svg>
		<span><?php echo esc_html( $settings['label'] ); ?></span>
	</a>
	<?php
}

function utr_export() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Forbidden', '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'utr_export' );
	$filters = utr_filters();
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="utm-traffic-' . gmdate( 'Y-m-d' ) . '.csv"' );
	$output = fopen( 'php://output', 'w' );
	fputcsv( $output, array( 'UTC time', 'Browser ID', 'Session ID', 'Page', 'Landing page', 'Referrer host', 'Referrer URL', 'UTM source', 'UTM medium', 'UTM campaign', 'UTM term', 'UTM content', 'UTM match type' ) );
	for ( $offset = 0; ; $offset += 500 ) {
		$rows = utr_query_rows( $filters, 500, $offset );
		if ( ! $rows ) {
			break;
		}
		foreach ( $rows as $row ) {
			$values = array( $row->occurred_at, $row->visitor_id, $row->session_id, $row->page_path, $row->landing_path, $row->referrer_host, $row->referrer_url, $row->utm_source, $row->utm_medium, $row->utm_campaign, $row->utm_term, $row->utm_content, $row->utm_match_type );
			// Prevent spreadsheet formula execution when the CSV is opened.
			fputcsv( $output, array_map( static function ( $value ) {
				return preg_match( '/^[\s]*[=+@-]/u', $value ) ? "'" . $value : $value;
			}, $values ) );
		}
		if ( count( $rows ) < 500 ) {
			break;
		}
	}
	fclose( $output );
	exit;
}
add_action( 'admin_post_utr_export', 'utr_export' );
