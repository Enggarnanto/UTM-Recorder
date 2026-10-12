<?php
/** Admin screens and account management for WhatsApp CTAs. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function utr_wa_account_defaults() {
	return array(
		'id'            => 0,
		'name'          => '',
		'phone'         => '',
		'title'         => '',
		'message'       => '',
		'avatar_id'     => 0,
		'days'          => range( 0, 6 ),
		'page_mode'     => 'all',
		'page_ids'      => array(),
		'show_front_page' => 0,
		'show_floating' => 1,
		'position'      => 'right',
		'bg_color'      => '#2563eb',
		'text_color'    => '#ffffff',
	);
}

function utr_wa_admin_redirect( $notice, $id = 0 ) {
	$args = array( 'page' => 'utr-whatsapp', 'utr_notice' => $notice );
	if ( $id ) {
		$args['edit'] = $id;
	}
	wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
	exit;
}

function utr_wa_save_account() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Tidak diizinkan.', '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'utr_wa_save_account' );
	$accounts = utr_wa_accounts();
	$id = isset( $_POST['account_id'] ) ? absint( $_POST['account_id'] ) : 0;
	if ( $id && ! isset( $accounts[ $id ] ) ) {
		utr_wa_admin_redirect( 'missing' );
	}
	$name = isset( $_POST['name'] ) && is_string( $_POST['name'] ) ? utr_clean_value( wp_unslash( $_POST['name'] ), 80 ) : '';
	$raw_phone = isset( $_POST['phone'] ) && is_string( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : '';
	$phone = preg_replace( '/[^0-9]/', '', $raw_phone );
	if ( '' === $name || ! utr_valid_whatsapp_phone( $phone ) ) {
		utr_wa_admin_redirect( 'invalid_account', $id );
	}
	$title = isset( $_POST['title'] ) && is_string( $_POST['title'] ) ? utr_clean_value( wp_unslash( $_POST['title'] ), 100 ) : '';
	$message = isset( $_POST['message'] ) && is_string( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
	$message = function_exists( 'mb_substr' ) ? mb_substr( $message, 0, 1000 ) : wp_check_invalid_utf8( substr( $message, 0, 1000 ), true );
	$avatar_id = isset( $_POST['avatar_id'] ) ? absint( $_POST['avatar_id'] ) : 0;
	if ( $avatar_id && ! wp_attachment_is_image( $avatar_id ) ) {
		$avatar_id = 0;
	}
	$raw_days = isset( $_POST['days'] ) && is_array( $_POST['days'] ) ? $_POST['days'] : array();
	$days = array_values( array_unique( array_intersect( range( 0, 6 ), array_map( 'intval', $raw_days ) ) ) );
	if ( ! $days ) {
		utr_wa_admin_redirect( 'invalid_days', $id );
	}
	$page_mode = isset( $_POST['page_mode'] ) && 'selected' === $_POST['page_mode'] ? 'selected' : 'all';
	$show_front_page = ! empty( $_POST['show_front_page'] ) ? 1 : 0;
	$raw_pages = isset( $_POST['page_ids'] ) && is_array( $_POST['page_ids'] ) ? array_map( 'absint', $_POST['page_ids'] ) : array();
	$page_ids = array_values( array_unique( array_filter( $raw_pages, static function ( $page_id ) {
		return 'page' === get_post_type( $page_id ) && 'publish' === get_post_status( $page_id );
	} ) ) );
	if ( 'selected' === $page_mode && ! $page_ids && ! $show_front_page ) {
		utr_wa_admin_redirect( 'invalid_pages', $id );
	}
	$background = isset( $_POST['bg_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['bg_color'] ) ) : '';
	$foreground = isset( $_POST['text_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['text_color'] ) ) : '';
	if ( ! $id ) {
		$minimum = $accounts ? max( array_map( 'intval', array_keys( $accounts ) ) ) + 1 : 1;
		$id = max( $minimum, absint( get_option( 'utr_wa_next_account_id', 1 ) ) );
		update_option( 'utr_wa_next_account_id', $id + 1, false );
	}
	$accounts[ $id ] = array(
		'id'            => $id,
		'name'          => $name,
		'phone'         => $phone,
		'title'         => $title,
		'message'       => $message,
		'avatar_id'     => $avatar_id,
		'days'          => $days,
		'page_mode'     => $page_mode,
		'page_ids'      => $page_ids,
		'show_front_page' => $show_front_page,
		'show_floating' => $id && isset( $accounts[ $id ] ) ? (int) ! empty( $accounts[ $id ]['show_floating'] ) : 1,
		'position'      => isset( $_POST['position'] ) && 'left' === $_POST['position'] ? 'left' : 'right',
		'bg_color'      => $background ?: '#2563eb',
		'text_color'    => $foreground ?: '#ffffff',
	);
	update_option( 'utr_wa_accounts', $accounts, false );
	utr_wa_admin_redirect( 'saved', $id );
}
add_action( 'admin_post_utr_wa_save_account', 'utr_wa_save_account' );

function utr_wa_delete_account() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Tidak diizinkan.', '', array( 'response' => 403 ) );
	}
	$id = isset( $_GET['account_id'] ) ? absint( $_GET['account_id'] ) : 0;
	check_admin_referer( 'utr_wa_delete_' . $id );
	$accounts = utr_wa_accounts();
	unset( $accounts[ $id ] );
	update_option( 'utr_wa_accounts', $accounts, false );
	utr_wa_admin_redirect( 'deleted' );
}
add_action( 'admin_post_utr_wa_delete_account', 'utr_wa_delete_account' );

function utr_wa_reorder_accounts() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Tidak diizinkan.' ), 403 );
	}
	check_ajax_referer( 'utr_wa_reorder' );
	$accounts = utr_wa_accounts();
	$raw_order = isset( $_POST['order'] ) && is_array( $_POST['order'] ) ? $_POST['order'] : array();
	$order = array_map( 'absint', $raw_order );
	$expected = array_map( 'intval', array_keys( $accounts ) );
	sort( $expected );
	$check = $order;
	sort( $check );
	if ( $check !== $expected ) {
		wp_send_json_error( array( 'message' => 'Urutan akun tidak valid.' ), 400 );
	}
	$sorted = array();
	foreach ( $order as $id ) {
		$sorted[ $id ] = $accounts[ $id ];
	}
	update_option( 'utr_wa_accounts', $sorted, false );
	wp_send_json_success( array( 'message' => 'Urutan akun tersimpan.' ) );
}
add_action( 'wp_ajax_utr_wa_reorder', 'utr_wa_reorder_accounts' );

function utr_wa_toggle_floating_account() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Tidak diizinkan.' ), 403 );
	}
	check_ajax_referer( 'utr_wa_reorder' );
	$id = isset( $_POST['account_id'] ) ? absint( $_POST['account_id'] ) : 0;
	$accounts = utr_wa_accounts();
	if ( ! $id || ! isset( $accounts[ $id ] ) ) {
		wp_send_json_error( array( 'message' => 'Akun tidak ditemukan.' ), 404 );
	}
	$accounts[ $id ]['show_floating'] = isset( $_POST['enabled'] ) && '1' === (string) $_POST['enabled'] ? 1 : 0;
	update_option( 'utr_wa_accounts', $accounts, false );
	wp_send_json_success( array( 'message' => 'Tampilan akun berhasil diperbarui.' ) );
}
add_action( 'wp_ajax_utr_wa_toggle_floating_account', 'utr_wa_toggle_floating_account' );

function utr_wa_save_global() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Tidak diizinkan.', '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'utr_wa_save_global' );
	$settings = utr_whatsapp_settings();
	$settings['floating_enabled'] = ! empty( $_POST['floating_enabled'] ) ? 1 : 0;
	$settings['enhance_links'] = ! empty( $_POST['enhance_links'] ) ? 1 : 0;
	$settings['floating_position'] = isset( $_POST['floating_position'] ) && 'left' === $_POST['floating_position'] ? 'left' : 'right';
	foreach ( array( 'floating_bg_color' => '#14213d', 'floating_text_color' => '#ffffff', 'floating_accent_color' => '#b9f227' ) as $key => $default ) {
		$color = isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? sanitize_hex_color( wp_unslash( $_POST[ $key ] ) ) : '';
		$settings[ $key ] = $color ?: $default;
	}
	$settings['floating_size'] = isset( $_POST['floating_size'] ) ? min( 140, max( 80, absint( $_POST['floating_size'] ) ) ) : 100;
	$greeting = isset( $_POST['greeting'] ) && is_string( $_POST['greeting'] ) ? sanitize_textarea_field( wp_unslash( $_POST['greeting'] ) ) : '';
	$settings['greeting'] = function_exists( 'mb_substr' ) ? mb_substr( $greeting, 0, 300 ) : substr( $greeting, 0, 300 );
	$message = isset( $_POST['message'] ) && is_string( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
	$settings['message'] = function_exists( 'mb_substr' ) ? mb_substr( $message, 0, 1000 ) : wp_check_invalid_utf8( substr( $message, 0, 1000 ), true );
	$label = isset( $_POST['source_label'] ) && is_string( $_POST['source_label'] ) ? utr_clean_value( wp_unslash( $_POST['source_label'] ), 50 ) : '';
	$settings['source_label'] = rtrim( $label, " \t:" ) ?: 'Sumber';
	$case = isset( $_POST['source_case'] ) && is_string( $_POST['source_case'] ) ? sanitize_key( wp_unslash( $_POST['source_case'] ) ) : 'original';
	$settings['source_case'] = in_array( $case, array( 'original', 'upper', 'title', 'lower' ), true ) ? $case : 'original';
	$raw_fields = isset( $_POST['source_fields'] ) && is_array( $_POST['source_fields'] ) ? array_map( 'sanitize_key', array_filter( wp_unslash( $_POST['source_fields'] ), 'is_string' ) ) : array();
	$settings['source_fields'] = array_values( array_intersect( array_keys( utr_wa_message_source_choices() ), $raw_fields ) );
	$separator = isset( $_POST['source_separator'] ) && is_string( $_POST['source_separator'] ) ? wp_check_invalid_utf8( wp_strip_all_tags( wp_unslash( $_POST['source_separator'] ) ), true ) : '+';
	$separator = preg_replace( '/[\x00-\x1f\x7f]+/', ' ', $separator );
	$settings['source_separator'] = function_exists( 'mb_substr' ) ? mb_substr( $separator, 0, 20 ) : wp_check_invalid_utf8( substr( $separator, 0, 20 ), true );
	$settings['fallback_enabled'] = ! empty( $_POST['fallback_enabled'] ) ? 1 : 0;
	$fallback_label = isset( $_POST['fallback_label'] ) && is_string( $_POST['fallback_label'] ) ? utr_clean_value( wp_unslash( $_POST['fallback_label'] ), 50 ) : '';
	$settings['fallback_label'] = rtrim( $fallback_label, " \t:" ) ?: 'Sumber';
	$settings['fallback_url_mode'] = isset( $_POST['fallback_url_mode'] ) && 'custom' === $_POST['fallback_url_mode'] ? 'custom' : 'page';
	$custom_url = isset( $_POST['fallback_custom_url'] ) && is_string( $_POST['fallback_custom_url'] ) ? esc_url_raw( wp_unslash( $_POST['fallback_custom_url'] ), array( 'http', 'https' ) ) : $settings['fallback_custom_url'];
	$parts = $custom_url ? wp_parse_url( $custom_url ) : false;
	if ( 'custom' === $settings['fallback_url_mode'] && $settings['fallback_enabled'] && ( ! $parts || empty( $parts['host'] ) || empty( $parts['scheme'] ) || ! in_array( $parts['scheme'], array( 'http', 'https' ), true ) ) ) {
		utr_wa_admin_redirect( 'invalid_fallback_url' );
	}
	$settings['fallback_custom_url'] = $parts && ! empty( $parts['host'] ) && ! empty( $parts['scheme'] ) && in_array( $parts['scheme'], array( 'http', 'https' ), true ) ? $custom_url : '';
	update_option( 'utr_whatsapp_settings', $settings, false );
	update_option( 'utr_wa_source_mode', isset( $_POST['wa_source_mode'] ) && 'session' === $_POST['wa_source_mode'] ? 'session' : 'local', false );
	utr_wa_admin_redirect( 'global_saved' );
}
add_action( 'admin_post_utr_wa_save_global', 'utr_wa_save_global' );

function utr_render_cta_manager() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Tidak diizinkan.' );
	}
	$accounts = utr_wa_accounts();
	$edit_id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
	$editing = $edit_id && isset( $accounts[ $edit_id ] );
	$form = wp_parse_args( $editing ? $accounts[ $edit_id ] : array(), utr_wa_account_defaults() );
	$settings = utr_whatsapp_settings();
	$floating_bg = sanitize_hex_color( $settings['floating_bg_color'] ) ?: '#14213d';
	$floating_text = sanitize_hex_color( $settings['floating_text_color'] ) ?: '#ffffff';
	$floating_accent = sanitize_hex_color( $settings['floating_accent_color'] ) ?: '#b9f227';
	$floating_size = min( 140, max( 80, absint( $settings['floating_size'] ) ) );
	$source_fields = is_array( $settings['source_fields'] ) ? $settings['source_fields'] : array( 'utm_source', 'session_id' );
	$recorded_fields = utr_recorded_utm_fields();
	$wa_only_fields = array_values( array_diff( array_intersect( utr_all_utm_fields(), $source_fields ), $recorded_fields ) );
	$pages = get_pages( array( 'post_status' => 'publish', 'sort_column' => 'post_title' ) );
	$notice = isset( $_GET['utr_notice'] ) && is_string( $_GET['utr_notice'] ) ? sanitize_key( wp_unslash( $_GET['utr_notice'] ) ) : '';
	$messages = array(
		'saved'           => 'Akun CTA berhasil disimpan.',
		'deleted'         => 'Akun CTA berhasil dihapus.',
		'global_saved'    => 'Pengaturan CTA WhatsApp berhasil disimpan.',
		'invalid_account' => 'Nama akun dan nomor WhatsApp internasional yang valid wajib diisi.',
		'invalid_days'    => 'Pilih setidaknya satu hari aktif.',
		'invalid_pages'   => 'Pilih setidaknya satu halaman untuk mode halaman tertentu.',
		'missing'         => 'Akun yang dipilih tidak ditemukan.',
		'invalid_fallback_url' => 'Masukkan tautan khusus lengkap dengan https:// atau pilih URL halaman saat ini.',
	);
	?>
	<div class="wrap utr-admin">
		<header class="utr-header"><div><p class="utr-eyebrow">PENGELOLAAN WHATSAPP</p><h1>CTA WA</h1><p class="utr-subtitle">Kelola akun, tampilan tombol, jadwal, dan halaman tempat CTA muncul.</p></div></header>
		<?php if ( isset( $messages[ $notice ] ) ) : ?><div class="notice <?php echo in_array( $notice, array( 'saved', 'deleted', 'global_saved' ), true ) ? 'notice-success' : 'notice-error'; ?> is-dismissible"><p><?php echo esc_html( $messages[ $notice ] ); ?></p></div><?php endif; ?>
		<div class="utr-cta-layout">
			<div>
				<section class="utr-panel utr-table-panel" aria-labelledby="utr-account-heading">
					<div class="utr-panel-heading"><div><h2 id="utr-account-heading">Akun WhatsApp</h2><p><?php echo esc_html( number_format_i18n( count( $accounts ) ) ); ?> akun. Geser baris untuk mengatur urutan, lalu gunakan sakelar di tabel untuk menampilkan atau menyembunyikan akun dari daftar chat.</p><span id="utr-order-status" role="status" aria-live="polite"></span></div><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=utr-whatsapp' ) ); ?>">+ Tambah akun</a></div>
				<?php if ( $accounts ) : ?><div class="utr-table-scroll"><table class="utr-table utr-account-table"><thead><tr><th>Nama akun</th><th>Avatar</th><th>Nomor</th><th>Judul</th><th>Hari aktif</th><th>Daftar chat</th><th>Shortcode</th><th>Aksi</th></tr></thead><tbody id="utr-account-sortable">
				<?php foreach ( $accounts as $account ) :
					$id = absint( $account['id'] );
					$avatar = ! empty( $account['avatar_id'] ) ? wp_get_attachment_image( absint( $account['avatar_id'] ), array( 40, 40 ), false, array( 'class' => 'utr-admin-avatar', 'alt' => '', 'sizes' => '40px', 'loading' => 'eager' ) ) : '';
					$days = isset( $account['days'] ) && is_array( $account['days'] ) ? array_map( 'intval', $account['days'] ) : array();
					$day_names = array_intersect_key( utr_wa_day_labels(), array_flip( $days ) );
					$delete_url = wp_nonce_url( add_query_arg( array( 'action' => 'utr_wa_delete_account', 'account_id' => $id ), admin_url( 'admin-post.php' ) ), 'utr_wa_delete_' . $id );
					?>
					<tr data-account-id="<?php echo esc_attr( $id ); ?>"><td data-label="Nama akun"><span class="utr-account-name"><button type="button" class="utr-drag-handle" aria-label="Geser urutan <?php echo esc_attr( $account['name'] ); ?>" title="Geser untuk mengubah urutan">&#9776;</button><strong><?php echo esc_html( $account['name'] ); ?></strong></span></td><td data-label="Avatar"><?php echo $avatar ? wp_kses_post( $avatar ) : '<span class="utr-admin-avatar-fallback">WA</span>'; ?></td><td data-label="Nomor"><?php echo esc_html( $account['phone'] ); ?></td><td data-label="Judul"><?php echo esc_html( $account['title'] ); ?></td><td data-label="Hari aktif"><?php echo esc_html( count( $days ) === 7 ? 'Setiap hari' : implode( ', ', $day_names ) ); ?></td><td data-label="Daftar chat"><label class="utr-table-toggle"><input class="utr-floating-account-toggle" type="checkbox" value="1" aria-label="Tampilkan <?php echo esc_attr( $account['name'] ); ?> di daftar chat mengambang" <?php checked( ! empty( $account['show_floating'] ) ); ?>><span class="utr-table-toggle-text"><?php echo ! empty( $account['show_floating'] ) ? 'Aktif' : 'Nonaktif'; ?></span></label></td><td data-label="Shortcode"><code>[utr_whatsapp id="<?php echo esc_attr( $id ); ?>"]</code></td><td data-label="Aksi"><span class="utr-account-actions"><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'utr-whatsapp', 'edit' => $id ), admin_url( 'admin.php' ) ) ); ?>">Edit</a> · <a class="utr-delete-link" href="<?php echo esc_url( $delete_url ); ?>" data-confirm="Hapus akun CTA ini? Data klik yang sudah tercatat tetap tersimpan.">Hapus</a></span></td></tr>
				<?php endforeach; ?></tbody></table></div>
				<?php else : ?><div class="utr-empty-state"><span class="dashicons dashicons-format-chat" aria-hidden="true"></span><h3>Belum ada akun CTA</h3><p>Isi panel di sebelah kanan untuk membuat akun pertama.</p></div><?php endif; ?>
				</section>
				<section class="utr-panel utr-settings-panel" aria-labelledby="utr-link-settings-heading"><h2 id="utr-link-settings-heading">Tombol mengambang &amp; tautan WhatsApp</h2><p>Satu tombol mengambang membuka daftar admin sesuai urutan tabel. Tautan yang sudah ada tetap memakai nomor dan pesannya sendiri.</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="utr_wa_save_global"><?php wp_nonce_field( 'utr_wa_save_global' ); ?>
						<label class="utr-switch-row"><input type="checkbox" name="floating_enabled" value="1" <?php checked( ! empty( $settings['floating_enabled'] ) ); ?>><span><strong>Tampilkan tombol WA mengambang</strong><small>Jika mati, daftar admin mengambang disembunyikan. Shortcode tetap aktif.</small></span></label>
						<label class="utr-field" for="utr-greeting">Salam pembuka daftar admin<textarea id="utr-greeting" name="greeting" rows="3" maxlength="300"><?php echo esc_textarea( $settings['greeting'] ); ?></textarea><small>Baris baru akan tampil di panel chat.</small></label>
						<label class="utr-field" for="utr-floating-position">Posisi tombol mengambang<select id="utr-floating-position" name="floating_position"><option value="right" <?php selected( 'right' === $settings['floating_position'] ); ?>>Kanan</option><option value="left" <?php selected( 'left' === $settings['floating_position'] ); ?>>Kiri</option></select></label>
						<fieldset class="utr-fieldset utr-floating-style"><legend>Tampilan tombol floating</legend>
							<p class="utr-setting-intro">Atur tombol pembuka chat yang tampil di sudut halaman.</p>
							<div class="utr-floating-color-grid">
								<label for="utr-floating-bg">Warna tombol<input id="utr-floating-bg" type="color" name="floating_bg_color" value="<?php echo esc_attr( $floating_bg ); ?>"></label>
								<label for="utr-floating-text">Warna teks &amp; ikon<input id="utr-floating-text" type="color" name="floating_text_color" value="<?php echo esc_attr( $floating_text ); ?>"></label>
								<label for="utr-floating-accent">Warna titik aksen<input id="utr-floating-accent" type="color" name="floating_accent_color" value="<?php echo esc_attr( $floating_accent ); ?>"></label>
							</div>
							<label class="utr-floating-size-control" for="utr-floating-size"><span>Ukuran tombol <output id="utr-floating-size-value" for="utr-floating-size"><?php echo esc_html( $floating_size ); ?>%</output></span><input id="utr-floating-size" type="range" name="floating_size" min="80" max="140" step="5" value="<?php echo esc_attr( $floating_size ); ?>"><small>80% lebih ringkas, 140% lebih besar. Ukuran panel daftar admin tetap nyaman dibaca.</small></label>
							<div class="utr-floating-preview"><span>Pratinjau tombol</span><div id="utr-floating-preview-launcher" class="utr-floating-preview-launcher" style="--utr-launcher-bg:<?php echo esc_attr( $floating_bg ); ?>;--utr-launcher-text:<?php echo esc_attr( $floating_text ); ?>;--utr-launcher-accent:<?php echo esc_attr( $floating_accent ); ?>;--utr-launcher-scale:<?php echo esc_attr( $floating_size / 100 ); ?>"><span class="utr-floating-preview-icon" aria-hidden="true"></span><strong>Chat WhatsApp</strong><i aria-hidden="true"></i></div></div>
							<button class="button button-primary" type="submit">Simpan tampilan tombol</button>
						</fieldset>
						<label class="utr-switch-row utr-switch-spaced"><input type="checkbox" name="enhance_links" value="1" <?php checked( ! empty( $settings['enhance_links'] ) ); ?>><span><strong>Tambahkan sumber ke tautan WA yang sudah ada</strong><small>Berlaku untuk api.whatsapp.com/send dan wa.me. Pelacakan klik diatur terpisah pada menu Settings.</small></span></label>
						<label class="utr-field" for="utr-global-message">Pesan default jika tautan belum punya pesan<textarea id="utr-global-message" name="message" rows="5" maxlength="1000"><?php echo esc_textarea( $settings['message'] ); ?></textarea><small>Tekan Enter untuk membuat paragraf. Baris sumber ditambahkan terpisah setelah pesan.</small></label><button class="button utr-insert-newline" type="button" data-target="utr-global-message">Tambah baris baru</button>
						<fieldset class="utr-fieldset utr-source-format"><legend>Format sumber dalam pesan WA</legend>
							<p>Atur baris yang ditambahkan setelah pesan akun, pesan default, atau pesan pada tautan WA yang didukung.</p>
							<div class="utr-source-choice-heading">UTM untuk pesan diambil dari</div>
							<div class="utr-source-mode-grid"><label class="utr-switch-row"><input type="radio" name="wa_source_mode" value="local" <?php checked( 'local' === utr_wa_source_mode() ); ?>><span><strong>Local storage</strong><small>UTM terbaru di browser, termasuk kunjungan berikutnya.</small></span></label><label class="utr-switch-row"><input type="radio" name="wa_source_mode" value="session" <?php checked( 'session' === utr_wa_source_mode() ); ?>><span><strong>Sesi aktif</strong><small>UTM hanya dari sesi tab saat ini.</small></span></label></div>
							<p class="utr-setting-note">Pilihan ini hanya mengubah teks pesan. Kolom UTM laporan klik tetap memakai atribusi terbaru dari local storage; pada mode sesi nilainya bisa berbeda dari pesan.</p>
							<label class="utr-field" for="utr-source-label">Kata sebelum nilai sumber<input id="utr-source-label" name="source_label" type="text" maxlength="50" value="<?php echo esc_attr( $settings['source_label'] ); ?>" placeholder="Sumber" required><small>Tanda titik dua ditambahkan otomatis. Contoh: Asal → Asal: GADS+12345678</small></label>
							<div class="utr-source-format-grid">
								<label class="utr-field" for="utr-source-case">Gaya huruf nilai<select id="utr-source-case" name="source_case"><option value="original" <?php selected( 'original' === $settings['source_case'] ); ?>>Sesuai nilai asli</option><option value="upper" <?php selected( 'upper' === $settings['source_case'] ); ?>>UPPERCASE</option><option value="title" <?php selected( 'title' === $settings['source_case'] ); ?>>Title Case</option><option value="lower" <?php selected( 'lower' === $settings['source_case'] ); ?>>lowercase</option></select></label>
								<label class="utr-field" for="utr-source-separator">Pemisah antar nilai<input id="utr-source-separator" name="source_separator" type="text" maxlength="20" value="<?php echo esc_attr( $settings['source_separator'] ); ?>" placeholder="+"><small>Misalnya +, - atau |. Kosongkan untuk menggabungkan tanpa pemisah.</small></label>
							</div>
							<div class="utr-source-choice-heading">Nilai yang ditambahkan, sesuai urutan berikut</div>
							<div class="utr-source-choice-grid"><?php foreach ( utr_wa_message_source_choices() as $field => $label ) : ?><label class="utr-switch-row"><input type="checkbox" name="source_fields[]" value="<?php echo esc_attr( $field ); ?>" <?php checked( in_array( $field, $source_fields, true ) ); ?>><span><strong><?php echo esc_html( $label ); ?></strong><small><?php echo esc_html( $field ); ?></small><?php if ( in_array( $field, utr_all_utm_fields(), true ) && ! in_array( $field, $recorded_fields, true ) ) : ?><small class="utr-wa-only-badge">Pesan WA saja · tidak masuk kolom laporan</small><?php endif; ?></span></label><?php endforeach; ?></div>
						<?php if ( $wa_only_fields ) : ?><p class="utr-setting-note utr-cross-setting-note">Parameter <strong><?php echo esc_html( implode( ', ', $wa_only_fields ) ); ?></strong> akan muncul di pesan WA, tetapi kolom UTM terkait pada laporan tetap kosong. Baris sumber pesan tetap dicatat pada riwayat klik. <a href="<?php echo esc_url( admin_url( 'admin.php?page=utr-settings' ) ); ?>">Ubah UTM laporan</a>.</p><?php endif; ?>
						<p class="utr-setting-note">ID sesi dan ID browser memakai 8 karakter awal. Jika semua kotak kosong, format UTM tidak ditambahkan; opsi tanpa UTM di bawah tetap dapat menambahkan URL.</p>
						</fieldset>
						<fieldset class="utr-fieldset utr-fallback-format"><legend>Saat tidak ada UTM</legend>
							<p class="utr-setting-intro">Atur baris sumber pengganti ketika browser pengunjung belum memiliki UTM. Pengaturan ini berlaku untuk tombol akun, shortcode, dan tautan WA yang didukung.</p>
							<label class="utr-switch-row"><input id="utr-fallback-enabled" type="checkbox" name="fallback_enabled" value="1" <?php checked( ! empty( $settings['fallback_enabled'] ) ); ?>><span><strong>Tambahkan sumber URL jika tidak ada UTM</strong><small>Jika dimatikan, pesan tanpa UTM tidak mendapat baris sumber.</small></span></label>
							<div id="utr-fallback-options" class="utr-fallback-options">
								<label class="utr-field" for="utr-fallback-label">Kata sebelum URL<input id="utr-fallback-label" name="fallback_label" type="text" maxlength="50" value="<?php echo esc_attr( $settings['fallback_label'] ); ?>" placeholder="Sumber"><small>Tanda titik dua ditambahkan otomatis.</small></label>
								<div class="utr-source-choice-heading">Link yang ditampilkan</div>
								<div class="utr-fallback-choices"><label class="utr-switch-row"><input type="radio" name="fallback_url_mode" value="page" <?php checked( 'page' === $settings['fallback_url_mode'] ); ?>><span><strong>URL halaman pengunjung</strong><small>Otomatis mengikuti halaman tempat tombol WA diklik.</small></span></label><label class="utr-switch-row"><input type="radio" name="fallback_url_mode" value="custom" <?php checked( 'custom' === $settings['fallback_url_mode'] ); ?>><span><strong>Link khusus</strong><small>Gunakan satu link tetap untuk semua halaman.</small></span></label></div>
								<label class="utr-field" for="utr-fallback-custom-url">Link khusus<input id="utr-fallback-custom-url" name="fallback_custom_url" type="url" maxlength="2048" value="<?php echo esc_attr( $settings['fallback_custom_url'] ); ?>" placeholder="https://example.com/halaman/"><small>Diisi hanya jika memilih Link khusus.</small></label>
								<div class="utr-message-preview"><strong>Pratinjau tanpa UTM</strong><pre id="utr-fallback-message-preview" aria-live="polite"></pre></div>
							</div>
						</fieldset>
						<?php submit_button( 'Simpan pengaturan CTA WA', 'secondary', 'submit', false ); ?>
					</form>
				</section>
			</div>
			<aside class="utr-panel utr-settings-panel utr-cta-editor" aria-labelledby="utr-editor-heading">
				<h2 id="utr-editor-heading"><?php echo $editing ? 'Edit akun CTA' : 'Tambah akun CTA'; ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="utr_wa_save_account"><input type="hidden" name="account_id" value="<?php echo esc_attr( $form['id'] ); ?>"><?php wp_nonce_field( 'utr_wa_save_account' ); ?>
					<label class="utr-field" for="utr-account-name">Nama akun<input id="utr-account-name" name="name" type="text" maxlength="80" required value="<?php echo esc_attr( $form['name'] ); ?>" placeholder="Contoh: Admin Penjualan"></label>
					<label class="utr-field" for="utr-account-phone">Nomor WhatsApp<input id="utr-account-phone" name="phone" type="tel" inputmode="numeric" maxlength="20" required value="<?php echo esc_attr( $form['phone'] ); ?>" placeholder="62XXXXXXXXXX"><small>Gunakan format internasional, tanpa + atau 0 di depan.</small></label>
					<label class="utr-field" for="utr-account-title">Judul / keterangan<input id="utr-account-title" name="title" type="text" maxlength="100" value="<?php echo esc_attr( $form['title'] ); ?>" placeholder="Konsultan Program"></label>
					<label class="utr-field" for="utr-account-message">Pesan akun<textarea id="utr-account-message" name="message" rows="6" maxlength="1000" placeholder="Halo kak, saya ingin bertanya...&#10;&#10;Apakah program ini masih tersedia?"><?php echo esc_textarea( $form['message'] ); ?></textarea><small>Tekan Enter untuk membuat paragraf. UTM atau URL halaman ditambahkan pada baris terpisah. Tautan lama yang sudah punya parameter text tetap memakai teks dari tautan tersebut.</small></label><button class="button utr-insert-newline" type="button" data-target="utr-account-message">Tambah baris baru</button>
					<div class="utr-message-preview"><strong>Pratinjau pesan dengan contoh UTM dan ID</strong><pre id="utr-account-message-preview" aria-live="polite"></pre></div>
					<div class="utr-field"><strong>Avatar</strong><input id="utr-avatar-id" type="hidden" name="avatar_id" value="<?php echo esc_attr( $form['avatar_id'] ); ?>"><div class="utr-avatar-picker"><div id="utr-avatar-preview"><?php echo $form['avatar_id'] ? wp_kses_post( wp_get_attachment_image( absint( $form['avatar_id'] ), array( 72, 72 ), false, array( 'sizes' => '72px', 'loading' => 'eager' ) ) ) : '<span class="utr-admin-avatar-fallback">WA</span>'; ?></div><button id="utr-avatar-select" class="button" type="button">Pilih avatar</button><button id="utr-avatar-remove" class="button" type="button">Hapus</button></div></div>
					<fieldset class="utr-fieldset"><legend>Hari aktif</legend><div class="utr-day-grid"><?php foreach ( utr_wa_day_labels() as $number => $day ) : ?><label><input type="checkbox" name="days[]" value="<?php echo esc_attr( $number ); ?>" <?php checked( in_array( $number, array_map( 'intval', $form['days'] ), true ) ); ?>><?php echo esc_html( $day ); ?></label><?php endforeach; ?></div></fieldset>
					<fieldset class="utr-fieldset"><legend>Ketersediaan halaman</legend><label><input type="radio" name="page_mode" value="all" <?php checked( 'all' === $form['page_mode'] ); ?>> Semua halaman publik</label><label><input type="radio" name="page_mode" value="selected" <?php checked( 'selected' === $form['page_mode'] ); ?>> Hanya halaman yang dipilih</label><label class="utr-front-page-option"><input type="checkbox" name="show_front_page" value="1" <?php checked( ! empty( $form['show_front_page'] ) ); ?>><span><strong>Beranda / homepage</strong><small>Centang ini untuk menampilkan akun di URL utama situs, termasuk beranda yang menampilkan posting.</small></span></label><div class="utr-page-list"><?php foreach ( $pages as $page ) : ?><label><input type="checkbox" name="page_ids[]" value="<?php echo esc_attr( $page->ID ); ?>" <?php checked( in_array( (int) $page->ID, array_map( 'intval', $form['page_ids'] ), true ) ); ?>><?php echo esc_html( $page->post_title ?: '(Tanpa judul)' ); ?></label><?php endforeach; ?><?php if ( ! $pages ) : ?><small>Belum ada halaman terbit.</small><?php endif; ?></div><small>Pilihan Beranda berlaku saat mode “Hanya halaman yang dipilih”. Halaman yang dicentang di bawah tetap tersedia seperti sebelumnya.</small></fieldset>
					<fieldset class="utr-fieldset"><legend>Desain custom</legend><div class="utr-color-row"><label>Warna tombol<input type="color" name="bg_color" value="<?php echo esc_attr( $form['bg_color'] ); ?>"></label><label>Warna teks<input type="color" name="text_color" value="<?php echo esc_attr( $form['text_color'] ); ?>"></label></div></fieldset>
					<p class="utr-setting-note">Tampilan akun di daftar chat mengambang dapat diubah langsung pada tabel akun di sebelah kiri. Shortcode tetap tersedia.</p>
					<?php submit_button( $editing ? 'Simpan perubahan' : 'Tambah akun', 'primary', 'submit', false ); ?>
				</form>
			</aside>
		</div>
		<p class="utr-footnote">Hari aktif dan pilihan halaman berlaku untuk tombol mengambang maupun shortcode. Klik dari tautan WhatsApp yang sudah ada dicatat sebagai “Tautan lain”.</p>
		<?php utr_render_brand_footer(); ?>
	</div>
	<?php
}

function utr_wa_source_label( $row ) {
	if ( ! empty( $row->message_source_recorded ) ) {
		return ! empty( $row->message_source ) ? $row->message_source : 'Tanpa sumber';
	}
	$source = ! empty( $row->utm_source ) ? $row->utm_source : home_url( isset( $row->page_path ) ? $row->page_path : '/' );
	$session = ! empty( $row->session_id ) ? '+' . substr( $row->session_id, 0, 8 ) : '';
	return 'Sumber: ' . $source . $session;
}

function utr_render_wa_dashboard() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Tidak diizinkan.' );
	}
	global $wpdb;
	$filters = utr_filters();
	$where = utr_where( $filters );
	$accounts = utr_wa_accounts();
	$account_id = isset( $_GET['account_id'] ) ? absint( $_GET['account_id'] ) : 0;
	if ( $account_id ) {
		$where .= $wpdb->prepare( ' AND account_id = %d', $account_id );
	}
	$table = utr_wa_click_table();
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$visitors = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT NULLIF(session_id, '')) FROM {$table} {$where}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$tagged = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where} AND (utm_source <> '' OR utm_medium <> '' OR utm_campaign <> '' OR utm_content <> '' OR utm_term <> '' OR utm_match_type <> '')" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$top_accounts = $wpdb->get_results( "SELECT account_id, CASE WHEN account_id = 0 THEN phone ELSE '' END AS external_phone, MAX(account_name) AS account_name, MAX(phone) AS phone, COUNT(DISTINCT NULLIF(session_id, '')) AS sessions, COUNT(*) AS clicks FROM {$table} {$where} GROUP BY account_id, CASE WHEN account_id = 0 THEN phone ELSE '' END ORDER BY sessions DESC, clicks DESC, account_name ASC LIMIT 5" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
	$page = min( $page, max( 1, (int) ceil( $total / 50 ) ) );
	$limit = 50;
	$offset = ( $page - 1 ) * $limit;
	$rows = $wpdb->get_results( "SELECT * FROM {$table} {$where} ORDER BY occurred_at DESC, id DESC LIMIT {$limit} OFFSET {$offset}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$base = admin_url( 'admin.php?page=utr-wa-dashboard' );
	$args = array( 'from' => $filters[0], 'to' => $filters[1], 'source' => $filters[2], 'account_id' => $account_id );
	?>
	<div class="wrap utr-admin"><header class="utr-header"><div><p class="utr-eyebrow">ANALITIK WHATSAPP</p><h1>Dashboard Chat WA</h1><p class="utr-subtitle">Lihat siapa yang diklik, halaman asal, dan parameter UTM lengkapnya.</p></div></header>
		<section class="utr-panel utr-filter-panel"><div class="utr-panel-heading"><h2>Filter klik</h2><span>Zona waktu: <?php echo esc_html( wp_timezone_string() ); ?></span></div><form class="utr-filters utr-manager-filters" method="get"><input type="hidden" name="page" value="utr-wa-dashboard"><label>Dari tanggal<input type="date" name="from" value="<?php echo esc_attr( $filters[0] ); ?>" required></label><label>Sampai tanggal<input type="date" name="to" value="<?php echo esc_attr( $filters[1] ); ?>" required></label><label>UTM source<input type="text" name="source" value="<?php echo esc_attr( $filters[2] ); ?>" placeholder="Semua sumber"></label><label>Akun<select name="account_id"><option value="0">Semua akun</option><?php foreach ( $accounts as $account ) : ?><option value="<?php echo esc_attr( $account['id'] ); ?>" <?php selected( $account_id === (int) $account['id'] ); ?>><?php echo esc_html( $account['name'] ); ?></option><?php endforeach; ?></select></label><button class="button button-secondary" type="submit">Terapkan filter</button><a class="utr-clear" href="<?php echo esc_url( $base ); ?>">Reset</a></form></section>
		<section class="utr-stats" aria-label="Ringkasan klik WhatsApp"><div class="utr-stat"><span class="utr-stat-icon dashicons dashicons-format-chat" aria-hidden="true"></span><span class="utr-stat-label">Total klik WA</span><strong><?php echo esc_html( number_format_i18n( $total ) ); ?></strong><small>Termasuk tautan WhatsApp yang sudah ada</small></div><div class="utr-stat"><span class="utr-stat-icon dashicons dashicons-groups" aria-hidden="true"></span><span class="utr-stat-label">Sesi pengunjung yang klik</span><strong><?php echo esc_html( number_format_i18n( $visitors ) ); ?></strong><small>Satu tab browser dihitung satu sesi</small></div><div class="utr-stat"><span class="utr-stat-icon dashicons dashicons-tag" aria-hidden="true"></span><span class="utr-stat-label">Klik dengan UTM tercatat</span><strong><?php echo esc_html( number_format_i18n( $tagged ) ); ?></strong><small>Berdasarkan kolom UTM yang aktif di Settings</small></div></section>
		<section class="utr-wa-ranking" aria-labelledby="utr-wa-ranking-heading">
			<div class="utr-panel-heading"><div><h2 id="utr-wa-ranking-heading">Top Akun/Nomor</h2><p>Urutan berdasarkan sesi unik yang mengklik, sesuai filter di atas.</p></div></div>
			<?php if ( $top_accounts ) : ?><ol class="utr-wa-top-grid">
				<?php foreach ( $top_accounts as $index => $top_account ) :
					$known = isset( $accounts[ (int) $top_account->account_id ] ) ? $accounts[ (int) $top_account->account_id ] : null;
					$name = $known ? $known['name'] : ( $top_account->account_id ? $top_account->account_name : 'Tautan nomor' );
					$phone = $known ? $known['phone'] : $top_account->phone;
					?>
					<li class="utr-panel utr-wa-top-card"><span class="utr-wa-top-rank">#<?php echo esc_html( $index + 1 ); ?></span><strong class="utr-wa-top-name"><?php echo esc_html( $name ); ?></strong><span class="utr-wa-top-phone"><?php echo esc_html( $phone ); ?></span><span class="utr-wa-top-count"><?php echo esc_html( number_format_i18n( $top_account->sessions ) ); ?> <small>sesi unik</small></span><small class="utr-wa-top-clicks"><?php echo esc_html( number_format_i18n( $top_account->clicks ) ); ?> klik</small></li>
				<?php endforeach; ?>
			</ol><?php else : ?><div class="utr-panel utr-wa-top-empty">Belum ada klik WA sesuai filter.</div><?php endif; ?>
		</section>
		<section class="utr-panel utr-table-panel"><div class="utr-panel-heading"><div><h2>Riwayat klik &amp; UTM</h2><p><?php echo esc_html( number_format_i18n( $total ) ); ?> klik sesuai filter. Kolom Sumber berisi teks yang ditambahkan ke pesan; kolom UTM mengikuti Settings. Klik judul kolom untuk mengurutkan halaman tabel ini.</p></div></div>
		<?php if ( $rows ) : ?><div class="utr-table-scroll"><table class="utr-table utr-detail-table utr-wa-history-table"><thead><tr><th>Waktu</th><th>Akun / nomor</th><th>Halaman</th><th>Sumber</th><th>ID sesi</th><th>Source</th><th>Medium</th><th>Campaign</th><th>Content</th><th>Term</th><th>Match Type</th></tr></thead><tbody>
		<?php foreach ( $rows as $row ) : ?><tr><td data-label="Waktu"><?php echo esc_html( wp_date( 'd M Y, H:i', strtotime( $row->occurred_at . ' UTC' ) ) ); ?></td><td data-label="Akun / nomor"><strong><?php echo esc_html( $row->account_name ); ?></strong><span class="utr-muted"><?php echo esc_html( $row->phone ); ?> · <?php echo esc_html( $row->link_type ); ?></span></td><td data-label="Halaman"><?php echo esc_html( $row->page_path ); ?></td><td data-label="Sumber"><strong><?php echo esc_html( utr_wa_source_label( $row ) ); ?></strong></td><td data-label="ID sesi"><?php echo '' !== $row->session_id ? '<code title="' . esc_attr( $row->session_id ) . '">' . esc_html( substr( $row->session_id, 0, 8 ) ) . '</code>' : '<span class="utr-muted">-</span>'; ?></td><td data-label="Source"><?php echo esc_html( $row->utm_source ); ?></td><td data-label="Medium"><?php echo esc_html( $row->utm_medium ); ?></td><td data-label="Campaign"><?php echo esc_html( $row->utm_campaign ); ?></td><td data-label="Content"><?php echo esc_html( $row->utm_content ); ?></td><td data-label="Term"><?php echo esc_html( $row->utm_term ); ?></td><td data-label="Match Type"><?php echo esc_html( $row->utm_match_type ); ?></td></tr><?php endforeach; ?>
		</tbody></table></div><?php if ( $total > 50 ) : ?><nav class="utr-pagination" aria-label="Halaman klik WA"><?php echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( array_merge( $args, array( 'paged' => '%#%' ) ), $base ), 'current' => $page, 'total' => (int) ceil( $total / 50 ), 'prev_text' => '&laquo; Sebelumnya', 'next_text' => 'Berikutnya &raquo;' ) ) ); ?></nav><?php endif; ?>
		<?php else : ?><div class="utr-empty-state"><span class="dashicons dashicons-format-chat" aria-hidden="true"></span><h3>Belum ada klik WA</h3><p>Coba ubah filter atau klik tombol WhatsApp pada situs untuk menguji pencatatan.</p></div><?php endif; ?></section>
		<p class="utr-footnote">Jumlah sesi adalah perkiraan pengunjung unik per tab browser. Data klik disimpan selama 90 hari.</p>
		<?php utr_render_brand_footer(); ?>
	</div>
	<?php
}
