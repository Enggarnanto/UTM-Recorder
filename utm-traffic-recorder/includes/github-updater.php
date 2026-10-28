<?php
/** Native WordPress updates from published ZIP assets, never GitHub source ZIPs. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function utr_github_release() {
	$cached = get_site_transient( 'utr_github_release' );
	if ( is_array( $cached ) ) { return $cached; }
	$result = array( 'error' => 'GitHub belum dapat dihubungi. Coba periksa lagi nanti.' );
	$response = wp_safe_remote_get( 'https://api.github.com/repos/Enggarnanto/UTM-Recorder/releases/latest', array(
		'timeout' => 15, 'redirection' => 0, 'limit_response_size' => 262144,
		'headers' => array( 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'UTM-Recorder/' . UTR_VERSION ),
	) );
	if ( ! is_wp_error( $response ) ) {
		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 404 === $code ) {
			$result['error'] = 'Belum ada GitHub Release publik. Publikasikan release dengan ZIP plugin untuk menyediakan pembaruan.';
		} elseif ( 403 === $code || 429 === $code ) {
			$result['error'] = 'Pemeriksaan GitHub dibatasi sementara. Coba lagi nanti.';
		} elseif ( 200 === $code && is_array( $data ) ) {
			$result = utr_github_parse_release( $data );
		}
	}
	set_site_transient( 'utr_github_release', $result, empty( $result['error'] ) ? 6 * HOUR_IN_SECONDS : 15 * MINUTE_IN_SECONDS );
	if ( function_exists( 'utr_activity_log' ) ) { utr_activity_log( 'update.check', empty( $result['error'] ) ? 'info' : 'warning', array( 'detail' => $result['error'] ?? 'Metadata GitHub Release diterima.', 'version' => $result['version'] ?? UTR_VERSION ) ); }
	return $result;
}

function utr_github_parse_release( $data ) {
	$invalid = array( 'error' => 'Release belum menyediakan ZIP instalasi yang sesuai. Gunakan tag vX.Y.Z dan asset utm-traffic-recorder.zip.' );
	if ( ! empty( $data['draft'] ) || ! empty( $data['prerelease'] ) || empty( $data['tag_name'] ) || ! is_string( $data['tag_name'] ) || ! preg_match( '/\Av?(\d+\.\d+\.\d+)\z/D', $data['tag_name'], $match ) || empty( $data['assets'] ) || ! is_array( $data['assets'] ) ) { return $invalid; }
	$version = $match[1];
	foreach ( array( 'utm-traffic-recorder.zip', 'utm-traffic-recorder-' . $version . '.zip' ) as $name ) {
		foreach ( $data['assets'] as $asset ) {
			if ( ! is_array( $asset ) || ( $asset['name'] ?? '' ) !== $name || ( $asset['state'] ?? '' ) !== 'uploaded' || empty( $asset['browser_download_url'] ) || ! is_string( $asset['browser_download_url'] ) ) { continue; }
			$parts = parse_url( $asset['browser_download_url'] );
			$expected = '/Enggarnanto/UTM-Recorder/releases/download/' . $data['tag_name'] . '/' . $name;
			if ( ! is_array( $parts ) || ( $parts['scheme'] ?? '' ) !== 'https' || strtolower( $parts['host'] ?? '' ) !== 'github.com' || rawurldecode( $parts['path'] ?? '' ) !== $expected || isset( $parts['user'], $parts['pass'] ) || isset( $parts['user'] ) || isset( $parts['port'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) ) { continue; }
			return array( 'version' => $version, 'package' => $asset['browser_download_url'], 'notes' => is_string( $data['body'] ?? null ) ? substr( $data['body'], 0, 50000 ) : '' );
		}
	}
	return $invalid;
}

function utr_github_update( $update, $plugin_data, $plugin_file, $locales ) {
	if ( plugin_basename( UTR_FILE ) !== $plugin_file ) { return $update; }
	$release = utr_github_release();
	if ( ! empty( $release['error'] ) ) { return $update; }
	return array(
		'id' => 'https://github.com/Enggarnanto/UTM-Recorder', 'slug' => 'utm-traffic-recorder',
		'version' => $release['version'], 'url' => 'https://github.com/Enggarnanto/UTM-Recorder/releases',
		'package' => $release['package'], 'requires_php' => '7.4',
	);
}
add_filter( 'update_plugins_github.com', 'utr_github_update', 10, 4 );

function utr_github_plugin_info( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || ! is_object( $args ) || 'utm-traffic-recorder' !== ( $args->slug ?? '' ) ) { return $result; }
	$release = utr_github_release();
	if ( ! empty( $release['error'] ) ) { return $result; }
	return (object) array(
		'name' => 'UTM Traffic Recorder', 'slug' => 'utm-traffic-recorder', 'version' => $release['version'],
		'author' => 'Galih Enggarnanto', 'homepage' => 'https://github.com/Enggarnanto/UTM-Recorder',
		'requires' => '6.0', 'requires_php' => '7.4', 'download_link' => $release['package'],
		'sections' => array( 'description' => 'Pencatatan trafik, UTM, klik WA, dan sinkronisasi data.', 'changelog' => '<pre style="white-space:pre-wrap">' . esc_html( $release['notes'] ) . '</pre>' ),
	);
}
add_filter( 'plugins_api', 'utr_github_plugin_info', 10, 3 );

function utr_github_check_now() {
	if ( ! current_user_can( 'update_plugins' ) ) { wp_die( 'Anda tidak memiliki izin memperbarui plugin.', '', array( 'response' => 403 ) ); }
	check_admin_referer( 'utr_check_update' );
	delete_site_transient( 'utr_github_release' );
	wp_clean_plugins_cache();
	wp_update_plugins();
	// wp_update_plugins may skip an overlapping check; ensure a fresh result for this view.
	utr_github_release();
	wp_safe_redirect( admin_url( 'admin.php?page=utr-settings#utr-plugin-updates' ) );
	exit;
}
add_action( 'admin_post_utr_check_update', 'utr_github_check_now' );

function utr_render_update_settings() {
	if ( ! current_user_can( 'update_plugins' ) ) { return; }
	// Rendering Settings never initiates a network request.
	$release = get_site_transient( 'utr_github_release' );
	?>
	<section class="utr-panel utr-settings-panel" id="utr-plugin-updates">
		<h2>Pembaruan plugin</h2>
		<p>Versi terpasang: <strong><?php echo esc_html( UTR_VERSION ); ?></strong>. Sumber pembaruan: GitHub Enggarnanto/UTM-Recorder.</p>
		<?php if ( is_array( $release ) ) : ?>
			<?php if ( ! empty( $release['error'] ) ) : ?><p><?php echo esc_html( $release['error'] ); ?></p>
			<?php elseif ( version_compare( $release['version'], UTR_VERSION, '>' ) ) : ?><p>Versi <strong><?php echo esc_html( $release['version'] ); ?></strong> tersedia. Buka <a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>">Plugins</a> dan klik <strong>Perbarui sekarang</strong>.</p>
			<?php else : ?><p>Tidak ada versi rilis yang lebih baru dari versi terpasang.</p><?php endif; ?>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="utr_check_update"><?php wp_nonce_field( 'utr_check_update' ); ?><button class="button" type="submit">Periksa pembaruan</button></form>
		<p class="utr-setting-note">Notifikasi dan tombol update tersedia setelah rilis publik dengan versi lebih tinggi beserta ZIP instalasi diterbitkan. Fitur ini tidak mengaktifkan auto-update tanpa pilihan administrator.</p>
	</section>
	<?php
}
