<?php
/** WhatsApp account rendering and click recording. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function utr_wa_click_table() {
	global $wpdb;
	return $wpdb->prefix . 'utm_traffic_wa_clicks';
}

function utr_wa_message_source_choices() {
	return array(
		'utm_source'     => 'UTM Source',
		'utm_medium'     => 'UTM Medium',
		'utm_campaign'   => 'UTM Campaign',
		'utm_content'    => 'UTM Content',
		'utm_term'       => 'UTM Term',
		'utm_match_type' => 'UTM Match Type',
		'session_id'     => 'ID Sesi',
		'visitor_id'     => 'ID Browser',
	);
}

function utr_install_wa_table() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table = utr_wa_click_table();
	$charset = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		occurred_at datetime NOT NULL,
		visitor_id varchar(36) NOT NULL DEFAULT '',
		session_id varchar(36) NOT NULL DEFAULT '',
		account_id bigint(20) unsigned NOT NULL DEFAULT 0,
		account_name varchar(191) NOT NULL DEFAULT '',
		phone varchar(20) NOT NULL DEFAULT '',
		link_type varchar(20) NOT NULL DEFAULT '',
		page_path varchar(2048) NOT NULL DEFAULT '',
		utm_source varchar(191) NOT NULL DEFAULT '',
		utm_medium varchar(191) NOT NULL DEFAULT '',
		utm_campaign varchar(191) NOT NULL DEFAULT '',
		utm_term varchar(191) NOT NULL DEFAULT '',
		utm_content varchar(191) NOT NULL DEFAULT '',
		utm_match_type varchar(191) NOT NULL DEFAULT '',
		message_source varchar(2048) NOT NULL DEFAULT '',
		message_source_recorded tinyint(1) NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		KEY occurred_at (occurred_at),
		KEY visitor_id (visitor_id),
		KEY account_id (account_id),
		KEY utm_source (utm_source)
	) {$charset};";
	dbDelta( $sql );
}

function utr_cleanup_wa_clicks( $cutoff ) {
	global $wpdb;
	$table = utr_wa_click_table();
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE occurred_at < %s", $cutoff ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

function utr_wa_accounts() {
	$accounts = get_option( 'utr_wa_accounts', array() );
	return is_array( $accounts ) ? $accounts : array();
}

function utr_wa_account( $id ) {
	$accounts = utr_wa_accounts();
	$id = absint( $id );
	return isset( $accounts[ $id ] ) && is_array( $accounts[ $id ] ) ? $accounts[ $id ] : null;
}

function utr_wa_day_labels() {
	return array( 'Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu' );
}

function utr_wa_upgrade() {
	if ( '1.5.0' === get_option( 'utr_wa_schema_version' ) ) {
		return;
	}
	utr_install_wa_table();
	$accounts = utr_wa_accounts();
	$legacy = function_exists( 'utr_whatsapp_settings' ) ? utr_whatsapp_settings() : array();
	if ( ! $accounts && ! empty( $legacy['enabled'] ) && ! empty( $legacy['phone'] ) ) {
		$accounts[1] = array(
			'id'            => 1,
			'name'          => 'Akun utama',
			'phone'         => $legacy['phone'],
			'title'         => ! empty( $legacy['label'] ) ? $legacy['label'] : 'Chat WhatsApp',
			'message'       => isset( $legacy['message'] ) ? $legacy['message'] : '',
			'avatar_id'     => 0,
			'days'          => range( 0, 6 ),
			'page_mode'     => 'all',
			'page_ids'      => array(),
			'show_floating' => 1,
			'position'      => 'right',
			'bg_color'      => '#2563eb',
			'text_color'    => '#ffffff',
		);
		update_option( 'utr_wa_accounts', $accounts, false );
		$legacy['enabled'] = 0;
		update_option( 'utr_whatsapp_settings', $legacy, false );
	}
	$stored_settings = get_option( 'utr_whatsapp_settings', array() );
	if ( ! is_array( $stored_settings ) || ! isset( $stored_settings['floating_position'] ) ) {
		$first = $accounts ? reset( $accounts ) : array();
		$legacy['floating_position'] = isset( $first['position'] ) && 'left' === $first['position'] ? 'left' : 'right';
		update_option( 'utr_whatsapp_settings', $legacy, false );
	}
	update_option( 'utr_wa_schema_version', '1.5.0', false );
}
add_action( 'init', 'utr_wa_upgrade', 1 );

function utr_wa_account_available( $account ) {
	$days = isset( $account['days'] ) && is_array( $account['days'] ) ? array_map( 'intval', $account['days'] ) : array();
	if ( ! in_array( (int) wp_date( 'w' ), $days, true ) ) {
		return false;
	}
	if ( isset( $account['page_mode'] ) && 'selected' === $account['page_mode'] ) {
		$pages = isset( $account['page_ids'] ) && is_array( $account['page_ids'] ) ? array_map( 'intval', $account['page_ids'] ) : array();
		if ( is_front_page() && ( ! empty( $account['show_front_page'] ) || in_array( (int) get_option( 'page_on_front', 0 ), $pages, true ) ) ) {
			return true;
		}
		if ( is_home() && in_array( (int) get_option( 'page_for_posts', 0 ), $pages, true ) ) {
			return true;
		}
		return $pages && is_page( $pages );
	}
	return true;
}

function utr_wa_button_markup( $account, $mode = 'shortcode' ) {
	if ( ! is_array( $account ) || ! utr_wa_account_available( $account ) ) {
		return '';
	}
	$id = isset( $account['id'] ) ? absint( $account['id'] ) : 0;
	$phone = isset( $account['phone'] ) ? $account['phone'] : '';
	if ( ! $id || ! utr_valid_whatsapp_phone( $phone ) ) {
		return '';
	}
	$background = isset( $account['bg_color'] ) ? sanitize_hex_color( $account['bg_color'] ) : '';
	$foreground = isset( $account['text_color'] ) ? sanitize_hex_color( $account['text_color'] ) : '';
	$style = '--utr-wa-bg:' . ( $background ?: '#2563eb' ) . ';--utr-wa-text:' . ( $foreground ?: '#ffffff' ) . ';';
	$name = isset( $account['name'] ) ? $account['name'] : 'WhatsApp';
	$title = isset( $account['title'] ) ? $account['title'] : '';
	$avatar_id = isset( $account['avatar_id'] ) ? absint( $account['avatar_id'] ) : 0;
	$avatar = $avatar_id ? wp_get_attachment_image( $avatar_id, 'thumbnail', false, array( 'class' => 'utr-wa-avatar', 'alt' => '', 'width' => '42', 'height' => '42', 'sizes' => '42px', 'loading' => 'eager', 'decoding' => 'sync' ) ) : '';
	$position = isset( $account['position'] ) && 'left' === $account['position'] ? 'left' : 'right';
	ob_start();
	?>
	<a class="utr-wa-account utr-wa-<?php echo esc_attr( $mode ); ?> utr-wa-<?php echo esc_attr( $position ); ?>" href="<?php echo esc_url( 'https://wa.me/' . $phone ); ?>" target="_blank" rel="noopener noreferrer" data-utr-account="<?php echo esc_attr( $id ); ?>" data-utr-type="<?php echo esc_attr( $mode ); ?>" data-utr-message-encoded="<?php echo esc_attr( rawurlencode( isset( $account['message'] ) ? $account['message'] : '' ) ); ?>" style="<?php echo esc_attr( $style ); ?>" aria-label="Chat WhatsApp dengan <?php echo esc_attr( $name ); ?>">
		<?php if ( $avatar ) : ?><span class="utr-wa-avatar-frame"><span class="utr-wa-avatar-fallback" aria-hidden="true">WA</span><?php echo wp_kses_post( $avatar ); ?></span><?php else : ?><span class="utr-wa-avatar-fallback" aria-hidden="true">WA</span><?php endif; ?>
		<span class="utr-wa-copy"><strong><?php echo esc_html( $name ); ?></strong><?php if ( '' !== $title ) : ?><small><?php echo esc_html( $title ); ?></small><?php endif; ?></span>
	</a>
	<?php
	return ob_get_clean();
}

function utr_wa_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'utr_whatsapp' );
	$account = utr_wa_account( $atts['id'] );
	return utr_wa_button_markup( $account, 'shortcode' );
}
add_shortcode( 'utr_whatsapp', 'utr_wa_shortcode' );

function utr_wa_render_floating_accounts() {
	static $rendered = false;
	$settings = utr_whatsapp_settings();
	if ( $rendered || empty( $settings['floating_enabled'] ) ) {
		return;
	}
	$items = array();
	foreach ( utr_wa_accounts() as $account ) {
		if ( empty( $account['show_floating'] ) ) {
			continue;
		}
		$html = utr_wa_button_markup( $account, 'floating' );
		if ( '' === $html ) {
			continue;
		}
		$items[] = $html;
	}
	if ( ! $items ) {
		return;
	}
	$rendered = true;
	$position = 'left' === $settings['floating_position'] ? 'left' : 'right';
	$greeting = trim( $settings['greeting'] );
	$launcher_bg = sanitize_hex_color( $settings['floating_bg_color'] ) ?: '#14213d';
	$launcher_text = sanitize_hex_color( $settings['floating_text_color'] ) ?: '#ffffff';
	$launcher_accent = sanitize_hex_color( $settings['floating_accent_color'] ) ?: '#b9f227';
	$launcher_size = min( 140, max( 80, absint( $settings['floating_size'] ) ) );
	$launcher_style = '--utr-launcher-bg:' . $launcher_bg . ';--utr-launcher-text:' . $launcher_text . ';--utr-launcher-accent:' . $launcher_accent . ';--utr-launcher-scale:' . ( $launcher_size / 100 ) . ';';
	?>
	<div class="utr-wa-widget utr-wa-widget-<?php echo esc_attr( $position ); ?>" id="utr-wa-widget" style="<?php echo esc_attr( $launcher_style ); ?>">
		<div class="utr-wa-panel" id="utr-wa-panel" hidden>
			<div class="utr-wa-panel-header"><div><strong>Chat dengan kami</strong><?php if ( $greeting ) : ?><p><?php echo nl2br( esc_html( $greeting ) ); ?></p><?php endif; ?></div><button class="utr-wa-close" type="button" aria-label="Tutup daftar WhatsApp">&times;</button></div>
			<div class="utr-wa-panel-list"><?php echo implode( '', $items ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by utr_wa_button_markup(). ?></div>
			<div class="utr-wa-panel-footer">&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <a href="https://www.mtalkblog.com/p/utm-traffic-recorder-dokumentasi.html" target="_blank" rel="noopener noreferrer">UTM Trafik Manager</a></div>
		</div>
		<button class="utr-wa-launcher" type="button" aria-label="Buka pilihan chat WhatsApp" aria-controls="utr-wa-panel" aria-expanded="false"><svg viewBox="0 0 24 24" width="27" height="27" fill="currentColor" aria-hidden="true" focusable="false"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg><span>Chat WhatsApp</span></button>
	</div>
	<?php
}
add_action( 'wp_body_open', 'utr_wa_render_floating_accounts', 5 );
add_action( 'wp_footer', 'utr_wa_render_floating_accounts', 5 );

function utr_wa_preload_avatars() {
	$settings = utr_whatsapp_settings();
	if ( empty( $settings['floating_enabled'] ) || ! utr_wa_accounts() ) {
		return;
	}
	$preloaded = array();
	foreach ( utr_wa_accounts() as $account ) {
		if ( empty( $account['show_floating'] ) || ! utr_wa_account_available( $account ) || empty( $account['avatar_id'] ) ) {
			continue;
		}
		$avatar_id = absint( $account['avatar_id'] );
		$url = wp_get_attachment_image_url( $avatar_id, 'thumbnail' );
		if ( $url && ! isset( $preloaded[ $url ] ) ) {
			$preloaded[ $url ] = true;
			$srcset = wp_get_attachment_image_srcset( $avatar_id, 'thumbnail' );
			echo '<link rel="preload" as="image" href="' . esc_url( $url ) . '"' . ( $srcset ? ' imagesrcset="' . esc_attr( $srcset ) . '" imagesizes="42px"' : '' ) . '>';
		}
	}
}
add_action( 'wp_head', 'utr_wa_preload_avatars', 2 );

function utr_wa_record_click( WP_REST_Request $request ) {
	global $wpdb;
	$site_host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	foreach ( array( $request->get_header( 'origin' ), $request->get_header( 'referer' ) ) as $header ) {
		if ( $header && strtolower( (string) wp_parse_url( $header, PHP_URL_HOST ) ) !== $site_host ) {
			return new WP_Error( 'utr_invalid_origin', 'Invalid request origin.', array( 'status' => 403 ) );
		}
	}
	$id = absint( $request->get_param( 'account_id' ) );
	$account = $id ? utr_wa_account( $id ) : null;
	if ( $id && ! $account ) {
		return new WP_Error( 'utr_invalid_account', 'Unknown WhatsApp account.', array( 'status' => 400 ) );
	}
	$raw_phone = $account ? $account['phone'] : $request->get_param( 'phone' );
	$phone = is_string( $raw_phone ) ? preg_replace( '/[^0-9]/', '', $raw_phone ) : '';
	if ( ! utr_valid_whatsapp_phone( $phone ) ) {
		return new WP_Error( 'utr_invalid_phone', 'Invalid WhatsApp number.', array( 'status' => 400 ) );
	}
	if ( ! $account ) {
		foreach ( utr_wa_accounts() as $candidate ) {
			if ( isset( $candidate['phone'] ) && $phone === $candidate['phone'] ) {
				$account = $candidate;
				$id = absint( $candidate['id'] );
				break;
			}
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
	$type = $request->get_param( 'link_type' );
	$type = in_array( $type, array( 'floating', 'shortcode', 'existing' ), true ) ? $type : 'existing';
	if ( 'existing' === $type && ! utr_external_wa_tracking_enabled() ) {
		return new WP_REST_Response( array( 'recorded' => false ), 200 );
	}
	$source_line = $request->get_param( 'message_source' );
	$source_line = is_string( $source_line ) ? wp_check_invalid_utf8( wp_strip_all_tags( $source_line ), true ) : '';
	$source_line = preg_replace( '/[\x00-\x1f\x7f]+/', ' ', $source_line );
	$source_line = function_exists( 'mb_substr' ) ? mb_substr( $source_line, 0, 2048 ) : wp_check_invalid_utf8( substr( $source_line, 0, 2048 ), true );
	$data = array(
		'occurred_at'   => current_time( 'mysql', true ),
		'visitor_id'   => $visitor,
		'session_id'   => $session,
		'account_id'   => $id,
		'account_name' => $account ? utr_clean_value( $account['name'] ) : 'Tautan lain',
		'phone'        => $phone,
		'link_type'    => $type,
		'page_path'    => utr_clean_path( $request->get_param( 'page_path' ) ),
		'utm_source'   => utr_recorded_utm_value( $request, 'utm_source' ),
		'utm_medium'   => utr_recorded_utm_value( $request, 'utm_medium' ),
		'utm_campaign' => utr_recorded_utm_value( $request, 'utm_campaign' ),
		'utm_term'     => utr_recorded_utm_value( $request, 'utm_term' ),
		'utm_content'  => utr_recorded_utm_value( $request, 'utm_content' ),
		'utm_match_type' => utr_recorded_utm_value( $request, 'utm_match_type' ),
		'message_source' => $source_line,
		'message_source_recorded' => 1,
	);
	if ( false === $wpdb->insert( utr_wa_click_table(), $data ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return new WP_Error( 'utr_storage_failed', 'Could not record WhatsApp click.', array( 'status' => 500 ) );
	}
	return new WP_REST_Response( array( 'recorded' => true ), 201 );
}

function utr_wa_register_click_route() {
	register_rest_route( 'utm-recorder/v1', '/wa-click', array(
		'methods'             => 'POST',
		'callback'            => 'utr_wa_record_click',
		'permission_callback' => '__return_true',
	) );
}
add_action( 'rest_api_init', 'utr_wa_register_click_route' );
