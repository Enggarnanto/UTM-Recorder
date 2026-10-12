<?php
/** Feature switches shared by traffic and WhatsApp tracking. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function utr_all_utm_fields() {
	return array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'utm_match_type' );
}

function utr_recorded_utm_fields() {
	$stored = get_option( 'utr_recorded_utm_fields', false );
	if ( false === $stored ) {
		return utr_all_utm_fields();
	}
	$stored = is_array( $stored ) ? array_filter( $stored, 'is_string' ) : array();
	return array_values( array_intersect( utr_all_utm_fields(), $stored ) );
}

function utr_recorded_utm_value( $request, $field ) {
	return in_array( $field, utr_recorded_utm_fields(), true ) ? utr_clean_value( $request->get_param( $field ) ) : '';
}

function utr_wa_source_mode() {
	return 'session' === get_option( 'utr_wa_source_mode', 'local' ) ? 'session' : 'local';
}

function utr_external_wa_tracking_enabled() {
	$stored = get_option( 'utr_track_external_wa_links', null );
	if ( null === $stored ) {
		$settings = utr_whatsapp_settings();
		return ! empty( $settings['enhance_links'] );
	}
	return (bool) $stored;
}

function utr_initialize_external_wa_tracking() {
	if ( null === get_option( 'utr_track_external_wa_links', null ) ) {
		$settings = utr_whatsapp_settings();
		update_option( 'utr_track_external_wa_links', empty( $settings['enhance_links'] ) ? 0 : 1, false );
	}
}
add_action( 'init', 'utr_initialize_external_wa_tracking', 3 );

function utr_save_feature_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Anda tidak memiliki izin untuk mengubah pengaturan ini.', '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'utr_save_feature_settings' );
	$raw_fields = isset( $_POST['utm_fields'] ) && is_array( $_POST['utm_fields'] ) ? array_map( 'sanitize_key', array_filter( wp_unslash( $_POST['utm_fields'] ), 'is_string' ) ) : array();
	update_option( 'utr_recorded_utm_fields', array_values( array_intersect( utr_all_utm_fields(), $raw_fields ) ), false );
	update_option( 'utr_track_external_wa_links', empty( $_POST['track_external_wa_links'] ) ? 0 : 1, false );
	update_option( 'utr_hide_utm_url', empty( $_POST['hide_utm_url'] ) ? 0 : 1, false );
	wp_safe_redirect( admin_url( 'admin.php?page=utr-settings&utr_notice=saved' ) );
	exit;
}
add_action( 'admin_post_utr_save_feature_settings', 'utr_save_feature_settings' );

function utr_render_feature_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Anda tidak memiliki izin untuk melihat halaman ini.' );
	}
	$fields = utr_recorded_utm_fields();
	$wa_settings = utr_whatsapp_settings();
	$wa_fields = isset( $wa_settings['source_fields'] ) && is_array( $wa_settings['source_fields'] ) ? array_intersect( utr_all_utm_fields(), $wa_settings['source_fields'] ) : array();
	$wa_only_fields = array_values( array_diff( $wa_fields, $fields ) );
	$labels = array(
		'utm_source'   => 'Source',
		'utm_medium'   => 'Medium',
		'utm_campaign' => 'Campaign',
		'utm_content'  => 'Content',
		'utm_term'     => 'Term',
		'utm_match_type' => 'Match Type',
	);
	$notice = isset( $_GET['utr_notice'] ) && is_string( $_GET['utr_notice'] ) ? sanitize_key( wp_unslash( $_GET['utr_notice'] ) ) : '';
	?>
	<div class="wrap utr-admin">
		<header class="utr-header"><div><p class="utr-eyebrow">KONTROL FITUR</p><h1>Settings</h1><p class="utr-subtitle">Atur kolom UTM laporan, pelacakan tautan, dan tampilan URL.</p></div></header>
		<?php if ( 'saved' === $notice ) : ?><div class="notice notice-success is-dismissible"><p>Pengaturan fitur berhasil disimpan.</p></div><?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="utr_save_feature_settings">
			<?php wp_nonce_field( 'utr_save_feature_settings' ); ?>
			<div class="utr-feature-grid">
				<section class="utr-panel utr-settings-panel" aria-labelledby="utr-fields-heading">
					<h2 id="utr-fields-heading">UTM untuk laporan</h2>
					<p class="utr-setting-intro">Pilih kolom UTM yang disimpan untuk kunjungan dan klik WA. Kolom laporan mengikuti atribusi terbaru di local storage. Teks sumber pesan diatur terpisah di CTA WA.</p>
					<div class="utr-field-checks">
						<?php foreach ( $labels as $field => $label ) : ?><label class="utr-switch-row"><input type="checkbox" name="utm_fields[]" value="<?php echo esc_attr( $field ); ?>" <?php checked( in_array( $field, $fields, true ) ); ?>><span><strong><?php echo esc_html( $label ); ?></strong><small><?php echo esc_html( $field ); ?></small></span></label><?php endforeach; ?>
					</div>
					<?php if ( $wa_only_fields ) : ?><p class="utr-setting-note utr-cross-setting-note">Dipakai di pesan WA, tetapi tidak masuk kolom UTM laporan: <strong><?php echo esc_html( implode( ', ', $wa_only_fields ) ); ?></strong>. Baris sumber pesan tetap tersimpan pada riwayat klik. <a href="<?php echo esc_url( admin_url( 'admin.php?page=utr-whatsapp' ) ); ?>">Lihat format pesan WA</a>.</p><?php endif; ?>
				</section>
				<section class="utr-panel utr-settings-panel" aria-labelledby="utr-link-track-heading">
					<h2 id="utr-link-track-heading">Pelacakan tautan WA</h2>
					<label class="utr-switch-row"><input type="checkbox" name="track_external_wa_links" value="1" <?php checked( utr_external_wa_tracking_enabled() ); ?>><span><strong>Catat klik tautan WhatsApp yang sudah ada</strong><small>Berlaku untuk api.whatsapp.com/send dan wa.me. Klik tombol CTA yang dibuat plugin tetap dicatat.</small></span></label>
					<p class="utr-setting-note">Penambahan teks sumber ke tautan lama diatur terpisah pada menu CTA WA.</p>
				</section>
				<section class="utr-panel utr-settings-panel" aria-labelledby="utr-clean-url-heading">
					<h2 id="utr-clean-url-heading">Clean URL</h2>
					<label class="utr-switch-row"><input type="checkbox" name="hide_utm_url" value="1" <?php checked( get_option( 'utr_hide_utm_url', 0 ) ); ?>><span><strong>Sembunyikan UTM dari address bar</strong><small>Parameter UTM yang didukung, termasuk utm_matchtype/utm_match_type, dibersihkan segera saat halaman dibuka. Parameter lain tetap ada; UTM tetap dapat dicatat.</small></span></label>
					<p class="utr-setting-note">Tautan kampanye yang dibagikan tetap harus memuat UTM. Pengaturan ini juga tampil di UTM Manager.</p>
				</section>
			</div>
			<div class="utr-settings-actions"><button class="button button-primary" type="submit">Simpan pengaturan</button></div>
		</form>
		<?php utr_render_brand_footer(); ?>
	</div>
	<?php
}
