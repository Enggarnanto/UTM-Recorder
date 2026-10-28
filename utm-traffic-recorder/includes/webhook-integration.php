<?php
/** Independent WA click export to an authenticated backend API. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function utr_webhook_config() {
	$value = get_option( 'utr_webhook_config', array() );
	return array_merge( array( 'enabled' => 0, 'endpoint' => '', 'site_id' => '', 'token' => '' ), is_array( $value ) ? $value : array() );
}

function utr_webhook_endpoint_valid( $url ) {
	$parts = is_string( $url ) ? parse_url( $url ) : false;
	return is_array( $parts ) && ( $parts['scheme'] ?? '' ) === 'https' && ! empty( $parts['host'] ) && ! empty( $parts['path'] ) && ! isset( $parts['user'] ) && ! isset( $parts['pass'] ) && ! isset( $parts['query'] ) && ! isset( $parts['fragment'] ) && ( ! isset( $parts['port'] ) || 443 === $parts['port'] );
}

function utr_webhook_ready( $config ) {
	return '' === utr_webhook_validation_error( $config );
}

function utr_webhook_validation_error( $config ) {
	if ( ! utr_webhook_endpoint_valid( $config['endpoint'] ) ) { return 'URL Webhook/API harus berupa alamat HTTPS lengkap, tanpa parameter query, username, atau password.'; }
	if ( ! is_string( $config['site_id'] ) || ! preg_match( '/\A[A-Za-z0-9_-]{1,64}\z/D', $config['site_id'] ) ) { return 'ID website wajib 1–64 karakter berupa huruf, angka, garis bawah, atau tanda hubung tanpa spasi.'; }
	if ( ! is_string( $config['token'] ) || strlen( $config['token'] ) < 32 ) { return 'Kunci API terlalu pendek atau belum diisi. Gunakan minimal 32 karakter; masukkan nilai kunci, bukan nama property.'; }
	if ( strlen( $config['token'] ) > 512 || preg_match( '/[^\x21-\x7e]/', $config['token'] ) ) { return 'Kunci API harus 32–512 karakter ASCII tanpa spasi atau baris baru.'; }
	return '';
}

function utr_webhook_schedule() {
	$config = utr_webhook_config();
	if ( $config['enabled'] && utr_webhook_ready( $config ) ) {
		if ( ! wp_next_scheduled( 'utr_webhook_sync' ) ) { wp_schedule_event( time() + 90, 'utr_five_minutes', 'utr_webhook_sync' ); }
	} elseif ( wp_next_scheduled( 'utr_webhook_sync' ) ) { wp_clear_scheduled_hook( 'utr_webhook_sync' ); }
}
add_action( 'init', 'utr_webhook_schedule', 11 );

function utr_webhook_request( $config, $events = null ) {
	if ( ! utr_webhook_ready( $config ) ) { return new WP_Error( 'webhook_config', utr_webhook_validation_error( $config ) ); }
	$payload = array( 'protocol_version' => 1, 'site_id' => $config['site_id'], 'site_url' => home_url( '/' ), 'action' => null === $events ? 'test' : 'sync', 'events' => null === $events ? array() : $events );
	$body = wp_json_encode( $payload );
	if ( false === $body || strlen( $body ) > 450000 ) { return new WP_Error( 'webhook_payload', 'Kiriman terlalu besar. Data belum ditandai terkirim.' ); }
	$response = wp_safe_remote_post( $config['endpoint'], array(
		'timeout' => 30, 'redirection' => 0, 'sslverify' => true, 'limit_response_size' => 65536,
		'headers' => array( 'Content-Type' => 'application/json; charset=utf-8', 'Accept' => 'application/json', 'Authorization' => 'Bearer ' . $config['token'] ), 'body' => $body,
	) );
	if ( is_wp_error( $response ) ) { return new WP_Error( 'webhook_network', 'API backend belum dapat dihubungi. Periksa HTTPS dan koneksi hosting. Batch tetap dalam antrean.' ); }
	$code = wp_remote_retrieve_response_code( $response );
	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( 403 === $code && is_array( $data ) && ( $data['error_code'] ?? '' ) === 'site_url_mismatch' ) { return new WP_Error( 'webhook_site_url', 'URL website tidak cocok dengan URL yang didaftarkan pada backend. Periksa domain dan subfolder WordPress.' ); }
	if ( 401 === $code || 403 === $code ) { return new WP_Error( 'webhook_auth', 'Kunci API atau ID website tidak diterima backend. Pastikan header Authorization diteruskan hosting.' ); }
	if ( $code >= 300 && $code < 400 ) { return new WP_Error( 'webhook_redirect', 'API mengarahkan ke halaman lain. Gunakan endpoint HTTPS final tanpa redirect atau login browser.' ); }
	if ( 404 === $code ) { return new WP_Error( 'webhook_missing', 'Endpoint API backend tidak ditemukan (HTTP 404). Minta IT memasang receiver dan memastikan route yang benar.' ); }
	if ( 200 !== $code || ! is_array( $data ) || true !== ( $data['ok'] ?? null ) ) {
		$known = array( 'site_url_mismatch' => 'URL website tidak cocok dengan website yang didaftarkan IT.', 'invalid_batch' => 'API menolak format data klik. IT perlu memeriksa kontrak protokol versi 1.', 'database_error' => 'Database backend belum mengonfirmasi penyimpanan; batch akan dicoba lagi.' );
		$error = is_array( $data ) && is_string( $data['error_code'] ?? null ) ? $data['error_code'] : '';
		return new WP_Error( 'webhook_response', $known[ $error ] ?? 'API belum mengembalikan JSON sukses (HTTP ' . $code . '). Posisi sinkronisasi tetap disimpan.' );
	}
	if ( ( $data['site_id'] ?? null ) !== $config['site_id'] ) { return new WP_Error( 'webhook_identity', 'ID website pada konfirmasi API tidak cocok.' ); }
	return $data;
}

function utr_webhook_status( $message, $success ) {
	if ( function_exists( 'utr_activity_log' ) ) { utr_activity_log( 'webhook.operation', $success ? 'info' : 'error', array( 'detail' => $message ) ); }
	update_option( 'utr_webhook_state', array( 'message' => $message, 'success' => (bool) $success, 'last_attempt' => time() ), false );
}

function utr_webhook_sync( $replay = false ) {
	$lock = utr_sheets_lock( 'utr_webhook_lock' );
	if ( ! $lock ) { return new WP_Error( 'webhook_busy', 'Sinkronisasi backend sedang berjalan. Coba lagi sebentar.' ); }
	try {
		global $wpdb;
		$config = utr_webhook_config();
		if ( ! $config['enabled'] || ! utr_webhook_ready( $config ) ) { return new WP_Error( 'webhook_disabled', 'Simpan konfigurasi dan aktifkan integrasi backend terlebih dahulu.' ); }
		if ( $replay ) { update_option( 'utr_webhook_cursor', 0, false ); }
		$cursor = (int) get_option( 'utr_webhook_cursor', 0 );
		$table = utr_wa_click_table();
		$wpdb->last_error = '';
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE id > %d ORDER BY id ASC LIMIT 100", $cursor ), ARRAY_A );
		if ( $wpdb->last_error || ! is_array( $rows ) ) {
			$result = new WP_Error( 'webhook_database', 'Riwayat klik WordPress belum dapat dibaca.' );
		} elseif ( ! $rows ) {
			$result = array( 'ok' => true, 'accepted_ids' => array() );
		} else {
			while ( count( $rows ) > 1 && strlen( wp_json_encode( $rows ) ) > 400000 ) { array_pop( $rows ); }
			$result = utr_webhook_request( $config, $rows );
			if ( ! is_wp_error( $result ) ) {
				$ids = array_map( 'strval', array_column( $rows, 'id' ) );
				if ( $ids !== ( $result['accepted_ids'] ?? null ) ) {
					$result = new WP_Error( 'webhook_ack', 'Konfirmasi ID klik tidak lengkap. Batch akan dikirim ulang tanpa melewati data.' );
				} else { update_option( 'utr_webhook_cursor', (int) end( $ids ), false ); }
			}
		}
		utr_webhook_status( is_wp_error( $result ) ? $result->get_error_message() : ( empty( $rows ) ? 'Semua riwayat yang tersedia sudah tersinkron ke backend.' : count( $rows ) . ' klik dikonfirmasi backend. Sisa antrean dilanjutkan otomatis.' ), ! is_wp_error( $result ) );
		return $result;
	} finally { utr_sheets_unlock( $lock, 'utr_webhook_lock' ); }
}
function utr_webhook_cron() { utr_webhook_sync(); }
add_action( 'utr_webhook_sync', 'utr_webhook_cron' );

function utr_webhook_admin_action() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Anda tidak memiliki izin mengubah integrasi.', '', array( 'response' => 403 ) ); }
	check_admin_referer( 'utr_webhook_action' );
	$operation = isset( $_POST['operation'] ) && is_string( $_POST['operation'] ) ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : '';
	if ( 'save' === $operation ) {
		$lock = utr_sheets_lock( 'utr_webhook_lock' );
		if ( ! $lock ) { utr_webhook_status( 'Sinkronisasi sedang berjalan; pengaturan belum disimpan. Coba lagi sebentar.', false ); }
		else {
			try {
				$old = utr_webhook_config(); $next = $old;
				foreach ( array( 'endpoint', 'site_id', 'token' ) as $field ) {
					$value = isset( $_POST[ $field ] ) && is_string( $_POST[ $field ] ) ? trim( wp_unslash( $_POST[ $field ] ) ) : '';
					if ( 'token' !== $field || '' !== $value ) { $next[ $field ] = $value; }
				}
				$next['enabled'] = empty( $_POST['enabled'] ) ? 0 : 1;
				if ( ! utr_webhook_ready( $next ) ) { utr_webhook_status( 'Tidak disimpan. ' . utr_webhook_validation_error( $next ), false ); }
				else {
					if ( $next['endpoint'] !== $old['endpoint'] || $next['site_id'] !== $old['site_id'] ) { update_option( 'utr_webhook_cursor', 0, false ); }
					update_option( 'utr_webhook_config', $next, false ); utr_webhook_schedule();
					utr_webhook_status( 'Pengaturan backend disimpan. ' . ( $next['enabled'] ? 'Antrean akan dikirim otomatis.' : 'Pengiriman otomatis nonaktif.' ), true );
				}
			} finally { utr_sheets_unlock( $lock, 'utr_webhook_lock' ); }
		}
	} elseif ( 'test' === $operation ) {
		$result = utr_webhook_request( utr_webhook_config() );
		utr_webhook_status( is_wp_error( $result ) ? $result->get_error_message() : 'Koneksi backend berhasil. Identitas website dan database diterima; tidak ada klik contoh ditambahkan.', ! is_wp_error( $result ) );
	} elseif ( in_array( $operation, array( 'sync', 'replay' ), true ) ) {
		$result = utr_webhook_sync( 'replay' === $operation );
		if ( is_wp_error( $result ) ) { utr_webhook_status( $result->get_error_message(), false ); }
	}
	wp_safe_redirect( admin_url( 'admin.php?page=utr-settings#utr-webhook-integration' ) ); exit;
}
add_action( 'admin_post_utr_webhook_action', 'utr_webhook_admin_action' );

function utr_render_webhook_settings() {
	global $wpdb;
	$config = utr_webhook_config(); $state = get_option( 'utr_webhook_state', array() );
	$cursor = (int) get_option( 'utr_webhook_cursor', 0 ); $table = utr_wa_click_table();
	$pending = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE id > %d", $cursor ) );
	?>
	<section class="utr-panel utr-settings-panel utr-sheets-settings" id="utr-webhook-integration">
		<h2>Integrasi Webhook/API</h2>
		<p>Kirim detail klik WA langsung ke sistem internal. Receiver API perlu dipasang oleh IT; bagian ini bukan alamat login dashboard backend.</p>
		<?php if ( ! empty( $state['message'] ) ) : ?><div class="notice <?php echo ! empty( $state['success'] ) ? 'notice-success' : 'notice-error'; ?> inline"><p><?php echo esc_html( $state['message'] ); ?></p></div><?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="utr_webhook_action"><input type="hidden" name="operation" value="save"><?php wp_nonce_field( 'utr_webhook_action' ); ?>
			<label class="utr-switch-row"><input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $config['enabled'] ) ); ?>><span><strong>Aktifkan sinkronisasi ke backend</strong><small>Setiap 5 menit, maksimal 100 klik per batch. Antrean terpisah dari Google Sheets.</small></span></label>
			<p><label for="utr-webhook-endpoint"><strong>URL Webhook/API</strong></label><br><input type="url" class="large-text" id="utr-webhook-endpoint" name="endpoint" value="<?php echo esc_attr( $config['endpoint'] ); ?>" maxlength="512" placeholder="https://sistem.example.com/api/integrations/utr/wa-clicks"></p>
			<p><label for="utr-webhook-site"><strong>ID website</strong></label><br><input type="text" class="regular-text" id="utr-webhook-site" name="site_id" value="<?php echo esc_attr( $config['site_id'] ); ?>" maxlength="64" pattern="[A-Za-z0-9_-]{1,64}" placeholder="website_a"><br><small>ID unik yang didaftarkan IT pada receiver. Perhatikan huruf besar/kecil.</small></p>
			<p><label for="utr-webhook-token"><strong>Kunci API</strong></label><br><input type="password" class="regular-text" id="utr-webhook-token" name="token" value="" autocomplete="new-password" maxlength="512" placeholder="<?php echo $config['token'] ? 'Tersimpan; kosongkan untuk mempertahankan' : 'Minimal 32 karakter'; ?>"><br><small>Gunakan kunci yang diberikan IT untuk website ini. Kunci dikirim melalui HTTPS dari server.</small></p>
			<button type="submit" class="button button-primary">Simpan integrasi backend</button>
		</form>
		<div class="utr-sheets-status"><p>Status: <strong><?php echo $config['enabled'] ? 'Aktif' : 'Nonaktif'; ?></strong> · Antrean: <?php echo esc_html( number_format_i18n( $pending ) ); ?> klik · ID terakhir dikonfirmasi: <?php echo esc_html( $cursor ); ?></p><?php if ( ! empty( $state['last_attempt'] ) ) : ?><p>Aktivitas terakhir: <?php echo esc_html( wp_date( 'd M Y H:i:s', $state['last_attempt'] ) ); ?>.</p><?php endif; ?></div>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="utr-sheets-tools"><input type="hidden" name="action" value="utr_webhook_action"><?php wp_nonce_field( 'utr_webhook_action' ); ?><button class="button" name="operation" value="test">Tes koneksi backend</button><button class="button" name="operation" value="sync">Sinkronkan ke backend</button><button class="button" name="operation" value="replay">Kirim ulang riwayat backend</button></form>
		<p class="utr-setting-note">Simpan sebelum tes. Google Sheets tetap dapat berjalan bersamaan. Pengiriman gagal tidak memajukan antrean. Waktu dikirim UTC dan dapat ditampilkan WIB di sistem internal. Jadwal mengikuti WP-Cron dan retensi WordPress tetap 90 hari.</p>
	</section>
	<?php
}
