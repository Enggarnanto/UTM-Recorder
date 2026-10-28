<?php
/** Optional server-side export of WhatsApp clicks to the Apps Script receiver. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function utr_sheets_config() {
	$value = get_option( 'utr_sheets_config', array() );
	return array_merge( array( 'enabled' => 0, 'endpoint' => '', 'site_id' => '', 'token' => '' ), is_array( $value ) ? $value : array() );
}

function utr_sheets_endpoint_valid( $url ) {
	return is_string( $url ) && 1 === preg_match( '~\Ahttps://script\.google\.com/macros/s/[A-Za-z0-9_-]+/exec\z~D', $url );
}

function utr_sheets_ready( $config ) {
	return utr_sheets_endpoint_valid( $config['endpoint'] ) && preg_match( '/\A[A-Za-z0-9_-]{1,64}\z/D', $config['site_id'] ) && strlen( $config['token'] ) >= 32;
}

function utr_sheets_schedules( $schedules ) {
	$schedules['utr_five_minutes'] = array( 'interval' => 300, 'display' => 'UTM Recorder: setiap 5 menit' );
	return $schedules;
}
add_filter( 'cron_schedules', 'utr_sheets_schedules' );

function utr_sheets_schedule() {
	$config = utr_sheets_config();
	if ( $config['enabled'] && utr_sheets_ready( $config ) ) {
		if ( ! wp_next_scheduled( 'utr_sheets_sync' ) ) {
			wp_schedule_event( time() + 60, 'utr_five_minutes', 'utr_sheets_sync' );
		}
	} elseif ( wp_next_scheduled( 'utr_sheets_sync' ) ) {
		wp_clear_scheduled_hook( 'utr_sheets_sync' );
	}
}
add_action( 'init', 'utr_sheets_schedule', 10 );

/** Atomic expiring lock shared by exports and settings changes. */
function utr_sheets_unlock( $value ) {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", 'utr_sheets_lock', $value ) );
	wp_cache_delete( 'utr_sheets_lock', 'options' );
}

function utr_sheets_lock() {
	$old = get_option( 'utr_sheets_lock', '' );
	if ( $old && (int) $old < time() ) {
		utr_sheets_unlock( $old );
	}
	$value = ( time() + 180 ) . ':' . wp_generate_uuid4();
	return add_option( 'utr_sheets_lock', $value, '', false ) ? $value : false;
}

/** Read Google's ContentService redirect without forwarding the POST body. */
function utr_sheets_transport( $endpoint, $body ) {
	$response = wp_safe_remote_post( $endpoint, array(
		'timeout' => 30, 'redirection' => 0, 'sslverify' => true,
		'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
		'body' => $body, 'limit_response_size' => 65536,
	) );
	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'sheets_network', 'Tidak dapat menghubungi Apps Script. Periksa koneksi HTTPS keluar pada hosting. Data belum ditandai terkirim.' );
	}
	if ( ! in_array( wp_remote_retrieve_response_code( $response ), array( 301, 302, 303, 307, 308 ), true ) ) {
		return $response;
	}
	$location = wp_remote_retrieve_header( $response, 'location' );
	$parts = is_string( $location ) ? parse_url( $location ) : false;
	if ( is_array( $parts ) && isset( $parts['host'] ) && 'accounts.google.com' === strtolower( $parts['host'] ) ) {
		return new WP_Error( 'sheets_login', 'Google meminta login. Atur akses Web app menjadi Siapa saja (tanpa syarat Akun Google), lalu Terapkan deployment.' );
	}
	if ( ! is_array( $parts ) || ! isset( $parts['scheme'], $parts['host'] ) || 'https' !== $parts['scheme'] || 'script.googleusercontent.com' !== strtolower( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || ( isset( $parts['port'] ) && 443 !== $parts['port'] ) ) {
		return new WP_Error( 'sheets_redirect', 'Redirect balasan Apps Script tidak dikenali. Periksa URL deployment /exec yang aktif. Data belum ditandai terkirim.' );
	}
	// Retry only reading this response URL, never repeat the original POST here.
	for ( $attempt = 0; $attempt < 3; ++$attempt ) {
		$response = wp_safe_remote_get( $location, array(
			'timeout' => 15, 'redirection' => 0, 'sslverify' => true,
			'headers' => array( 'Accept' => 'application/json' ),
			'limit_response_size' => 65536,
		) );
		if ( ! is_wp_error( $response ) && ! in_array( wp_remote_retrieve_response_code( $response ), array( 404, 429, 500, 502, 503, 504 ), true ) ) {
			return $response;
		}
		if ( $attempt < 2 ) {
			usleep( 250000 * ( $attempt + 1 ) );
		}
	}
	return new WP_Error( 'sheets_response_fetch', 'Kiriman sudah mencapai Apps Script, tetapi konfirmasi dari script.googleusercontent.com belum dapat dibaca. Data mungkin sudah masuk Sheets; posisi sinkronisasi tetap disimpan dan batch akan dicoba ulang tanpa duplikat.' );
}

function utr_sheets_request( $config, $extra ) {
	if ( ! utr_sheets_ready( $config ) ) {
		return new WP_Error( 'sheets_config', 'Lengkapi URL /exec, ID website, dan kunci minimal 32 karakter.' );
	}
	$body = wp_json_encode( array_merge( array( 'site_id' => $config['site_id'], 'site_url' => home_url( '/' ), 'token' => $config['token'] ), $extra ) );
	if ( false === $body || strlen( $body ) > 450000 ) {
		return new WP_Error( 'sheets_payload', 'Ukuran kiriman terlalu besar. Data belum ditandai terkirim.' );
	}
	$response = utr_sheets_transport( $config['endpoint'], $body );
	if ( is_wp_error( $response ) ) {
		return $response;
	}
	$code = wp_remote_retrieve_response_code( $response );
	$result = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( 200 !== $code || ! is_array( $result ) || ! isset( $result['ok'] ) || true !== $result['ok'] ) {
		$message = is_array( $result ) && isset( $result['error'] ) && 'Unauthorized' === $result['error']
			? 'Kunci atau ID website tidak cocok dengan Script Properties.'
			: ( is_array( $result ) ? 'Receiver mengembalikan JSON gagal (HTTP ' . $code . '). Periksa SPREADSHEET_ID, izin spreadsheet, header tabel, dan versi deployment. Posisi sinkronisasi belum dimajukan.' : 'Balasan Apps Script bukan JSON yang diharapkan (HTTP ' . $code . '). Periksa URL /exec aktif dan akses Siapa saja. Data belum dikonfirmasi; jangan hapus riwayat.' );
		return new WP_Error( 'sheets_receiver', $message );
	}
	if ( ! isset( $result['site_id'] ) || $config['site_id'] !== $result['site_id'] ) {
		return new WP_Error( 'sheets_identity', 'Konfirmasi ID website dari Apps Script tidak cocok.' );
	}
	return $result;
}

function utr_sheets_set_status( $message, $success ) {
	$state = get_option( 'utr_sheets_state', array() );
	$state = is_array( $state ) ? $state : array();
	$state['message'] = $message;
	$state['success'] = (bool) $success;
	$state['last_attempt'] = time();
	update_option( 'utr_sheets_state', $state, false );
}

/** One ordered batch per run. Only a complete acknowledgement advances the cursor. */
function utr_sheets_sync( $replay = false ) {
	$config = utr_sheets_config();
	if ( ! $config['enabled'] || ! utr_sheets_ready( $config ) ) {
		return new WP_Error( 'sheets_disabled', 'Simpan konfigurasi dan aktifkan sinkronisasi terlebih dahulu.' );
	}
	$lock = utr_sheets_lock();
	if ( ! $lock ) {
		return new WP_Error( 'sheets_busy', 'Sinkronisasi sedang berjalan. Coba lagi sebentar.' );
	}
	try {
		global $wpdb;
		// Settings may have changed while this request was waiting for the lock.
		$config = utr_sheets_config();
		if ( ! $config['enabled'] || ! utr_sheets_ready( $config ) ) {
			return new WP_Error( 'sheets_disabled', 'Sinkronisasi telah dinonaktifkan.' );
		}
		if ( $replay ) {
			update_option( 'utr_sheets_cursor', 0, false );
		}
		$cursor = (int) get_option( 'utr_sheets_cursor', 0 );
		$table = utr_wa_click_table();
		$wpdb->last_error = '';
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE id > %d ORDER BY id ASC LIMIT 100", $cursor ), ARRAY_A );
		if ( $wpdb->last_error || ! is_array( $rows ) ) {
			$result = new WP_Error( 'sheets_database', 'Riwayat klik belum dapat dibaca. Coba lagi setelah tabel plugin tersedia.' );
		} elseif ( ! $rows ) {
			$result = array( 'inserted' => 0 );
		} else {
			// Bound the JSON size; wide historic rows are sent in smaller batches.
			while ( count( $rows ) > 1 && strlen( wp_json_encode( $rows ) ) > 400000 ) {
				array_pop( $rows );
			}
			$result = utr_sheets_request( $config, array( 'events' => $rows ) );
			if ( ! is_wp_error( $result ) ) {
				$sent = array_map( 'strval', array_column( $rows, 'id' ) );
				$ack = isset( $result['accepted_ids'] ) && is_array( $result['accepted_ids'] ) ? $result['accepted_ids'] : array();
				if ( $sent !== $ack ) {
					$result = new WP_Error( 'sheets_ack', 'Konfirmasi klik tidak lengkap. Batch akan dicoba ulang tanpa melewati data.' );
				} else {
					update_option( 'utr_sheets_cursor', (int) end( $sent ), false );
				}
			}
		}
		if ( is_wp_error( $result ) ) {
			utr_sheets_set_status( $result->get_error_message(), false );
		} else {
			utr_sheets_set_status( empty( $rows ) ? 'Semua riwayat yang tersedia sudah tersinkron.' : count( $rows ) . ' klik dikonfirmasi oleh Sheets. Sisa antrean dilanjutkan otomatis.', true );
		}
		return $result;
	} finally {
		utr_sheets_unlock( $lock );
	}
}
// Avoid passing cron arguments as the replay flag.
function utr_sheets_cron() {
	utr_sheets_sync();
}
add_action( 'utr_sheets_sync', 'utr_sheets_cron' );

function utr_sheets_admin_action() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Anda tidak memiliki izin untuk mengubah integrasi.', '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'utr_sheets_action' );
	$operation = isset( $_POST['operation'] ) && is_string( $_POST['operation'] ) ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : '';
	if ( 'save' === $operation ) {
		$config = utr_sheets_config();
		$next = $config;
		foreach ( array( 'endpoint', 'site_id', 'token' ) as $field ) {
			$value = isset( $_POST[ $field ] ) && is_string( $_POST[ $field ] ) ? trim( wp_unslash( $_POST[ $field ] ) ) : '';
			if ( 'token' !== $field || '' !== $value ) {
				$next[ $field ] = $value;
			}
		}
		$next['enabled'] = empty( $_POST['enabled'] ) ? 0 : 1;
		// Empty fields are allowed only for an integration that has never been configured.
		$has_config = $next['endpoint'] || $next['site_id'] || $next['token'] || $next['enabled'];
		if ( $has_config && ( ! utr_sheets_ready( $next ) || strlen( $next['token'] ) > 512 || preg_match( '/[\x00-\x20\x7f]/', $next['token'] ) ) ) {
			utr_sheets_set_status( 'Tidak disimpan. Gunakan URL https://script.google.com/macros/s/.../exec, ID website tanpa spasi, dan kunci 32–512 karakter tanpa spasi.', false );
		} else {
			$lock = utr_sheets_lock();
			if ( ! $lock ) {
				utr_sheets_set_status( 'Pengaturan belum disimpan karena sinkronisasi sedang berjalan. Coba lagi sebentar.', false );
			} else {
				try {
					if ( $next['endpoint'] !== $config['endpoint'] || $next['site_id'] !== $config['site_id'] ) {
						update_option( 'utr_sheets_cursor', 0, false );
						delete_option( 'utr_sheets_state' );
					}
					update_option( 'utr_sheets_config', $next, false );
					utr_sheets_schedule();
					utr_sheets_set_status( 'Pengaturan integrasi disimpan.' . ( $next['enabled'] ? ' Riwayat yang belum tersinkron akan dikirim otomatis.' : ' Sinkronisasi otomatis dinonaktifkan.' ), true );
				} finally {
					utr_sheets_unlock( $lock );
				}
			}
		}
	} elseif ( 'test' === $operation ) {
		$result = utr_sheets_request( utr_sheets_config(), array( 'action' => 'test' ) );
		utr_sheets_set_status( is_wp_error( $result ) ? $result->get_error_message() : 'Koneksi berhasil: kunci diterima dan tabel Sheets dapat diakses. Tidak ada klik contoh yang ditambahkan.', ! is_wp_error( $result ) );
	} elseif ( in_array( $operation, array( 'sync', 'replay' ), true ) ) {
		$result = utr_sheets_sync( 'replay' === $operation );
		if ( is_wp_error( $result ) ) {
			utr_sheets_set_status( $result->get_error_message(), false );
		}
	}
	wp_safe_redirect( admin_url( 'admin.php?page=utr-settings#utr-google-sheets' ) );
	exit;
}
add_action( 'admin_post_utr_sheets_action', 'utr_sheets_admin_action' );

function utr_render_sheets_settings() {
	global $wpdb;
	$config = utr_sheets_config();
	$state = get_option( 'utr_sheets_state', array() );
	$cursor = (int) get_option( 'utr_sheets_cursor', 0 );
	$table = utr_wa_click_table();
	$pending = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE id > %d", $cursor ) );
	?>
	<section class="utr-panel utr-settings-panel utr-sheets-settings" id="utr-google-sheets" aria-labelledby="utr-sheets-heading">
		<h2 id="utr-sheets-heading">Integrasi Google Sheets</h2>
		<p>Kumpulkan detail setiap klik WhatsApp dari berbagai website ke satu spreadsheet. Data dikirim dari server; kunjungan biasa tidak dikirim.</p>
		<?php if ( ! empty( $state['message'] ) ) : ?><div class="notice <?php echo ! empty( $state['success'] ) ? 'notice-success' : 'notice-error'; ?> inline"><p><?php echo esc_html( $state['message'] ); ?></p></div><?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="utr_sheets_action"><input type="hidden" name="operation" value="save">
			<?php wp_nonce_field( 'utr_sheets_action' ); ?>
			<label class="utr-switch-row"><input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $config['enabled'] ) ); ?>><span><strong>Aktifkan sinkronisasi klik WA</strong><small>Setiap 5 menit, maksimal 100 klik per pengiriman. Riwayat yang masih tersimpan ikut dikirim.</small></span></label>
			<p><label for="utr-sheets-endpoint"><strong>URL Apps Script</strong></label><br><input class="large-text" type="url" id="utr-sheets-endpoint" name="endpoint" value="<?php echo esc_attr( $config['endpoint'] ); ?>" placeholder="https://script.google.com/macros/s/.../exec" maxlength="512"><small>Gunakan deployment Web app: Execute as Me, akses Anyone, dan URL berakhir /exec.</small></p>
			<p><label for="utr-sheets-site"><strong>ID website</strong></label><br><input class="regular-text" type="text" id="utr-sheets-site" name="site_id" value="<?php echo esc_attr( $config['site_id'] ); ?>" pattern="[A-Za-z0-9_-]{1,64}" maxlength="64" placeholder="web_a"><br><small>Harus unik untuk setiap website. Contoh web_a menggunakan Script Property SITE_KEY_web_a.</small></p>
			<p><label for="utr-sheets-key"><strong>Kunci koneksi</strong></label><br><input class="regular-text" type="password" id="utr-sheets-key" name="token" value="" autocomplete="new-password" maxlength="512" placeholder="<?php echo $config['token'] ? 'Tersimpan; kosongkan untuk mempertahankan' : 'Minimal 32 karakter'; ?>"><br><small>Isi Value dari SITE_KEY_&lt;ID website&gt;. Kunci tidak ditampilkan kembali dan hanya dikirim dari server ke Apps Script.</small></p>
			<p class="utr-setting-note">Mengganti URL penerima atau ID website memulai sinkronisasi riwayat dari awal. Pertahankan ID website agar kiriman ulang dikenali sebagai data yang sama.</p>
			<button class="button button-primary" type="submit">Simpan integrasi</button>
		</form>
		<div class="utr-sheets-status"><p><strong>Status:</strong> <?php echo $config['enabled'] ? 'Aktif' : 'Nonaktif'; ?> · <strong>Antrean:</strong> <?php echo esc_html( number_format_i18n( $pending ) ); ?> klik · <strong>ID klik terakhir dikonfirmasi:</strong> <?php echo esc_html( $cursor ); ?></p>
		<?php if ( ! empty( $state['last_attempt'] ) ) : ?><p>Aktivitas terakhir: <?php echo esc_html( wp_date( 'd M Y H:i:s', $state['last_attempt'] ) ); ?> (zona waktu WordPress).</p><?php endif; ?>
		</div>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="utr-sheets-tools">
			<input type="hidden" name="action" value="utr_sheets_action"><?php wp_nonce_field( 'utr_sheets_action' ); ?>
			<button class="button" type="submit" name="operation" value="test">Tes koneksi</button>
			<button class="button" type="submit" name="operation" value="sync">Sinkronkan sekarang</button>
			<button class="button" type="submit" name="operation" value="replay">Sinkronkan ulang riwayat</button>
		</form>
		<p class="utr-setting-note">Simpan integrasi sebelum menggunakan tombol di atas. Sinkronkan sekarang mengirim satu batch; sisanya dilanjutkan otomatis. Pengiriman gagal dicoba lagi tanpa melewati data. Sinkronkan ulang riwayat mengirim kembali data yang masih tersimpan; receiver mencegah duplikat.</p>
		<p class="utr-setting-note">Jadwal mengikuti WP-Cron dan aktivitas website. Untuk jadwal konsisten di website sepi, atur cron hosting. Retensi riwayat plugin tetap 90 hari; data yang sudah dihapus dari WordPress tidak dapat dikirim ulang. Sheets menyimpan waktu klik dalam UTC.</p>
	</section>
	<?php
}
