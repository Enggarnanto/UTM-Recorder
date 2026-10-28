<?php
/** Plugin-wide audit trail. No request bodies, visitor identifiers, or API keys. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function utr_activity_table() { global $wpdb; return $wpdb->prefix . 'utm_activity_log'; }

function utr_install_activity_table() {
	global $wpdb;
	$table = utr_activity_table(); $charset = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		occurred_at datetime NOT NULL,
		level varchar(12) NOT NULL DEFAULT 'info',
		category varchar(24) NOT NULL,
		event varchar(64) NOT NULL,
		message varchar(512) NOT NULL,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		context longtext NOT NULL,
		PRIMARY KEY  (id),
		KEY occurred_at (occurred_at),
		KEY category_level (category, level)
	) {$charset};" );
}

function utr_activity_catalog() {
	return array(
		'plugin.activated' => 'Plugin diaktifkan atau skema diperbarui.',
		'plugin.deactivated' => 'Plugin dinonaktifkan.',
		'plugin.updated' => 'Versi plugin diperbarui.',
		'plugin.cleanup' => 'Pembersihan riwayat dan log dijalankan.',
		'settings.saved' => 'Pengaturan plugin berubah.',
		'tracking.pageview' => 'Kunjungan berhasil dicatat.',
		'tracking.wa_click' => 'Klik WhatsApp berhasil dicatat.',
		'tracking.rejected' => 'Permintaan pelacakan ditolak.',
		'sheets.operation' => 'Hasil operasi Google Sheets.',
		'webhook.operation' => 'Hasil operasi Webhook/API.',
		'update.check' => 'Pemeriksaan GitHub Release selesai.',
		'log.exported' => 'Log aktivitas diekspor.',
	);
}

function utr_activity_redact( $value ) {
	$text = strip_tags( (string) $value );
	foreach ( array( 'utr_sheets_config', 'utr_webhook_config' ) as $name ) {
		$config = get_option( $name, array() );
		if ( is_array( $config ) && is_string( $config['token'] ?? null ) && '' !== $config['token'] ) { $text = str_replace( $config['token'], '[REDACTED]', $text ); }
	}
	$text = preg_replace( '~https?://[^\s<>]+~i', '[URL]', $text );
	$text = preg_replace( '/Bearer\s+[^\s,;]+/i', 'Bearer [REDACTED]', $text );
	$text = preg_replace( '/((?:token|password|secret|api[_ -]?key|kunci|authorization)\s*[:=]\s*)[^\s,;]+/i', '$1[REDACTED]', $text );
	return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 400, 'UTF-8' ) : substr( $text, 0, 400 );
}

function utr_activity_log( $event, $level = 'info', $context = array() ) {
	global $wpdb;
	$catalog = utr_activity_catalog();
	if ( ! is_string( $event ) || ! isset( $catalog[ $event ] ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'insert' ) ) { return false; }
	$context = is_array( $context ) ? $context : array();
	$category = explode( '.', $event )[0]; $safe = array();
	foreach ( array( 'count', 'cursor', 'http_status' ) as $field ) {
		if ( isset( $context[ $field ] ) && is_numeric( $context[ $field ] ) ) { $safe[ $field ] = max( 0, (int) $context[ $field ] ); }
	}
	foreach ( array( 'version', 'from_version', 'to_version', 'option', 'action', 'endpoint' ) as $field ) {
		if ( isset( $context[ $field ] ) && is_string( $context[ $field ] ) && preg_match( '/\A[A-Za-z0-9_.-]{1,80}\z/D', $context[ $field ] ) ) { $safe[ $field ] = $context[ $field ]; }
	}
	if ( in_array( $category, array( 'sheets', 'webhook', 'update' ), true ) && isset( $context['detail'] ) && is_scalar( $context['detail'] ) ) { $safe['detail'] = utr_activity_redact( $context['detail'] ); }
	$data = array( 'occurred_at' => gmdate( 'Y-m-d H:i:s' ), 'level' => in_array( $level, array( 'info', 'warning', 'error' ), true ) ? $level : 'info', 'category' => $category, 'event' => $event, 'message' => $catalog[ $event ], 'user_id' => function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0, 'context' => wp_json_encode( $safe ) );
	$previous = method_exists( $wpdb, 'suppress_errors' ) ? $wpdb->suppress_errors( true ) : null;
	try { return false !== $wpdb->insert( utr_activity_table(), $data ); }
	catch ( Throwable $error ) { return false; }
	finally { if ( null !== $previous ) { $wpdb->suppress_errors( $previous ); } }
}

function utr_activity_setting_changed( $option ) {
	$tracked = array( 'utr_recorded_utm_fields', 'utr_track_external_wa_links', 'utr_hide_utm_url', 'utr_wa_source_mode', 'utr_whatsapp_settings', 'utr_wa_accounts', 'utr_sheets_config', 'utr_webhook_config' );
	if ( in_array( $option, $tracked, true ) ) { utr_activity_log( 'settings.saved', 'info', array( 'option' => $option ) ); }
}
add_action( 'updated_option', 'utr_activity_setting_changed', 10, 1 );
add_action( 'added_option', 'utr_activity_setting_changed', 10, 1 );

function utr_activity_tracking( $result, $server, $request ) {
	$routes = array( '/utm-recorder/v1/hit' => 'tracking.pageview', '/utm-recorder/v1/wa-click' => 'tracking.wa_click' );
	$route = $request->get_route();
	if ( ! isset( $routes[ $route ] ) || ! is_object( $result ) || ! method_exists( $result, 'get_status' ) ) { return $result; }
	$status = (int) $result->get_status();
	if ( $status >= 400 ) { utr_activity_log( 'tracking.rejected', 'warning', array( 'http_status' => $status, 'endpoint' => basename( $route ) ) ); }
	elseif ( $status >= 200 && $status < 300 && method_exists( $result, 'get_data' ) ) {
		$data = $result->get_data();
		if ( is_array( $data ) && ! empty( $data['recorded'] ) ) { utr_activity_log( $routes[ $route ], 'info', array( 'count' => 1 ) ); }
	}
	return $result;
}
add_filter( 'rest_post_dispatch', 'utr_activity_tracking', 10, 3 );

function utr_activity_cleanup() {
	global $wpdb; $table = utr_activity_table();
	utr_activity_log( 'plugin.cleanup' );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE occurred_at < %s", gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ) ) );
	$cutoff = $wpdb->get_var( "SELECT id FROM {$table} ORDER BY id DESC LIMIT 1 OFFSET 49999" );
	if ( $cutoff ) { $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id < %d", $cutoff ) ); }
}
add_action( 'utr_daily_cleanup', 'utr_activity_cleanup', 20 );

function utr_activity_version_changed( $option, $old, $new ) {
	if ( 'utr_loaded_version' === $option && $old !== $new ) { utr_activity_log( 'plugin.updated', 'info', array( 'from_version' => (string) $old, 'to_version' => (string) $new ) ); }
}
add_action( 'updated_option', 'utr_activity_version_changed', 10, 3 );

function utr_activity_filters() {
	$level = isset( $_GET['utr_level'] ) && is_string( $_GET['utr_level'] ) ? sanitize_key( wp_unslash( $_GET['utr_level'] ) ) : '';
	$category = isset( $_GET['utr_category'] ) && is_string( $_GET['utr_category'] ) ? sanitize_key( wp_unslash( $_GET['utr_category'] ) ) : '';
	return array( 'level' => in_array( $level, array( 'info', 'warning', 'error' ), true ) ? $level : '', 'category' => in_array( $category, array( 'plugin', 'settings', 'tracking', 'sheets', 'webhook', 'update', 'log' ), true ) ? $category : '' );
}

function utr_activity_where( $filters ) {
	global $wpdb; $where = 'WHERE 1=1'; $args = array();
	foreach ( array( 'level', 'category' ) as $field ) {
		if ( ! empty( $filters[ $field ] ) ) { $where .= " AND {$field} = %s"; $args[] = $filters[ $field ]; }
	}
	return $args ? $wpdb->prepare( $where, $args ) : $where;
}

function utr_render_activity_log() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Anda tidak memiliki izin melihat log aktivitas.' ); }
	global $wpdb; $table = utr_activity_table(); $filters = utr_activity_filters(); $where = utr_activity_where( $filters );
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where}" );
	$pages = max( 1, (int) ceil( $total / 50 ) );
	$page = isset( $_GET['utr_page'] ) && is_scalar( $_GET['utr_page'] ) ? max( 1, min( $pages, absint( $_GET['utr_page'] ) ) ) : 1;
	$offset = ( $page - 1 ) * 50;
	$rows = $wpdb->get_results( "SELECT * FROM {$table} {$where} ORDER BY id DESC LIMIT 50 OFFSET {$offset}" );
	$base = array( 'page' => 'utr-activity-log', 'utr_level' => $filters['level'], 'utr_category' => $filters['category'] );
	$export = wp_nonce_url( add_query_arg( array( 'action' => 'utr_export_activity', 'utr_level' => $filters['level'], 'utr_category' => $filters['category'] ), admin_url( 'admin-post.php' ) ), 'utr_export_activity' );
	?>
	<div class="wrap utr-admin"><header class="utr-header"><div><p class="utr-eyebrow">MONITORING</p><h1>Log aktivitas</h1><?php utr_render_header_version(); ?><p class="utr-subtitle">Aktivitas plugin, pelacakan, pengaturan, integrasi, dan pembaruan.</p></div><a class="button" href="<?php echo esc_url( $export ); ?>">Ekspor CSV (maks. 10.000)</a></header><hr class="wp-header-end">
	<section class="utr-panel utr-settings-panel"><form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="utr-filters"><input type="hidden" name="page" value="utr-activity-log"><label>Level<select name="utr_level"><option value="">Semua level</option><?php foreach ( array( 'info', 'warning', 'error' ) as $level ) : ?><option value="<?php echo esc_attr( $level ); ?>" <?php selected( $filters['level'], $level ); ?>><?php echo esc_html( strtoupper( $level ) ); ?></option><?php endforeach; ?></select></label><label>Kategori<select name="utr_category"><option value="">Semua kategori</option><?php foreach ( array( 'plugin', 'settings', 'tracking', 'sheets', 'webhook', 'update', 'log' ) as $category ) : ?><option value="<?php echo esc_attr( $category ); ?>" <?php selected( $filters['category'], $category ); ?>><?php echo esc_html( $category ); ?></option><?php endforeach; ?></select></label><button class="button" type="submit">Terapkan filter</button></form>
	<p><?php echo esc_html( number_format_i18n( $total ) ); ?> catatan. Waktu mengikuti zona waktu WordPress. User ID 0 berarti sistem/pengunjung.</p>
	<div class="utr-table-scroll"><table class="utr-table"><thead><tr><th>Waktu</th><th>Level</th><th>Kategori</th><th>Aktivitas</th><th>User ID</th><th>Detail</th></tr></thead><tbody>
	<?php if ( $rows ) : foreach ( $rows as $row ) : ?><tr><td data-label="Waktu"><?php echo esc_html( wp_date( 'd M Y H:i:s', strtotime( $row->occurred_at . ' UTC' ) ) ); ?></td><td data-label="Level"><?php echo esc_html( strtoupper( $row->level ) ); ?></td><td data-label="Kategori"><?php echo esc_html( $row->category ); ?></td><td data-label="Aktivitas"><?php echo esc_html( $row->message ); ?><br><small><?php echo esc_html( $row->event ); ?></small></td><td data-label="User ID"><?php echo esc_html( $row->user_id ); ?></td><td data-label="Detail"><code class="utr-activity-context"><?php echo esc_html( $row->context ); ?></code></td></tr><?php endforeach; else : ?><tr><td colspan="6">Belum ada log untuk filter ini.</td></tr><?php endif; ?>
	</tbody></table></div><p class="utr-pagination"><?php if ( $page > 1 ) : ?><a class="button" href="<?php echo esc_url( add_query_arg( array_merge( $base, array( 'utr_page' => $page - 1 ) ), admin_url( 'admin.php' ) ) ); ?>">Sebelumnya</a><?php endif; ?> Halaman <?php echo esc_html( $page ); ?> / <?php echo esc_html( $pages ); ?> <?php if ( $page < $pages ) : ?><a class="button" href="<?php echo esc_url( add_query_arg( array_merge( $base, array( 'utr_page' => $page + 1 ) ), admin_url( 'admin.php' ) ) ); ?>">Berikutnya</a><?php endif; ?></p>
	<p class="utr-setting-note">Log dicatat sejak versi 2.6.16. Retensi 30 hari dan pemangkasan ke 50.000 catatan dijalankan harian lewat WP-Cron. Kunci API, header otorisasi, isi payload, nomor WA, dan identitas browser/sesi tidak disimpan di log. Riwayat trafik/klik tetap ada pada laporan masing-masing. Ini log plugin, bukan seluruh aktivitas WordPress atau server.</p></section><?php utr_render_brand_footer(); ?></div>
	<?php
}

function utr_export_activity() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Anda tidak memiliki izin mengekspor log.', '', array( 'response' => 403 ) ); }
	check_admin_referer( 'utr_export_activity' );
	global $wpdb; $table = utr_activity_table(); $where = utr_activity_where( utr_activity_filters() );
	$rows = $wpdb->get_results( "SELECT occurred_at, level, category, event, message, user_id, context FROM {$table} {$where} ORDER BY id DESC LIMIT 10000", ARRAY_A );
	utr_activity_log( 'log.exported', 'info', array( 'count' => is_array( $rows ) ? count( $rows ) : 0 ) );
	nocache_headers(); header( 'Content-Type: text/csv; charset=utf-8' ); header( 'Content-Disposition: attachment; filename="utm-activity-log.csv"' );
	$stream = fopen( 'php://output', 'w' );
	if ( $stream ) {
		fputcsv( $stream, array( 'occurred_at_utc', 'level', 'category', 'event', 'message', 'user_id', 'context' ) );
		foreach ( (array) $rows as $row ) { fputcsv( $stream, array_map( function( $value ) { $text = (string) $value; return preg_match( '/^[=+\-@\s]/', $text ) ? "'" . $text : $text; }, array_values( $row ) ) ); }
		fclose( $stream );
	}
	exit;
}
add_action( 'admin_post_utr_export_activity', 'utr_export_activity' );
