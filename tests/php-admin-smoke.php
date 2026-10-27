<?php
// Exercise save handlers and frontend rendering with an in-memory WordPress option store.
define( 'ABSPATH', __DIR__ . '/' );
$options = array();
$actions = array();
$purges = 0;
$nonces = array();
class TestRedirect extends RuntimeException {}
function add_action( $name, $callback ) { global $actions; $actions[$name][] = $callback; }
function do_action( $name, ...$args ) { global $actions, $purges; if ( 'litespeed_purge_all' === $name ) { ++$purges; } foreach ( $actions[$name] ?? array() as $callback ) { $callback(...$args); } }
function add_shortcode() {}
function register_activation_hook() {}
function register_deactivation_hook() {}
function current_user_can() { return true; }
function check_admin_referer( $name ) { global $nonces; $nonces[] = $name; }
function get_option( $name, $default = false ) { global $options; return $options[$name] ?? $default; }
function update_option( $name, $value, $autoload = false ) { global $options; $old = $options[$name] ?? null; $options[$name] = $value; if ( $old !== $value ) { do_action( 'updated_option', $name, $old, $value ); } return true; }
function wp_parse_args( $values, $defaults ) { return array_merge( $defaults, $values ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_unslash( $value ) { return is_array($value) ? array_map('wp_unslash', $value) : stripslashes($value); }
function sanitize_text_field( $value ) { return trim(preg_replace('/\s+/', ' ', strip_tags($value))); }
function sanitize_textarea_field( $value ) { return trim(strip_tags($value)); }
function sanitize_key( $value ) { return preg_replace('/[^a-z0-9_-]/', '', strtolower($value)); }
function sanitize_hex_color( $value ) { return preg_match('/^#[a-f0-9]{6}$/i', $value) ? $value : ''; }
function wp_check_invalid_utf8( $value ) { return $value; }
function wp_strip_all_tags( $value ) { return strip_tags($value); }
function esc_url_raw( $value ) { return filter_var($value, FILTER_VALIDATE_URL) ? $value : ''; }
function wp_parse_url( $url, $component = -1 ) { return parse_url($url, $component); }
function admin_url( $path ) { return 'https://example.com/wp-admin/' . $path; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query($args); }
function wp_safe_redirect( $url ) { throw new TestRedirect($url); }
function wp_die( $message ) { throw new RuntimeException($message); }
function esc_html( $value ) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function esc_attr( $value ) { return esc_html($value); }
function esc_url( $value ) { return esc_attr($value); }
function wp_kses_post( $value ) { return $value; }
function wp_date( $format ) { return date($format); }
require dirname(__DIR__) . '/utm-traffic-recorder/utm-traffic-recorder.php';
function check( $value, $message ) { if (!$value) { throw new RuntimeException($message); } }
function submit( $handler, $post, $notice ) {
    $_POST = $post;
    try { $handler(); throw new RuntimeException('Missing redirect'); }
    catch (TestRedirect $redirect) { check(str_contains($redirect->getMessage(), 'utr_notice=' . $notice), 'Unexpected notice: ' . $redirect->getMessage()); }
}
$options['utr_whatsapp_settings'] = array_merge(utr_whatsapp_settings(), array('message'=>'Default lama', 'fallback_enabled'=>1, 'fallback_url_mode'=>'custom', 'fallback_custom_url'=>''));
submit('utr_wa_save_appearance', array('floating_enabled'=>1, 'greeting'=>"Halo baru!\nPilih admin.", 'panel_title'=>'Konsultasi', 'floating_position'=>'left'), 'appearance_saved');
$saved = utr_whatsapp_settings();
check($saved['greeting'] === "Halo baru!\nPilih admin.", 'Greeting not saved');
check($saved['panel_title'] === 'Konsultasi' && $saved['floating_position'] === 'left', 'Appearance not saved');
check($saved['message'] === 'Default lama' && $saved['fallback_url_mode'] === 'custom', 'Appearance save changed message settings');
check($purges === 1, 'Cache purge not dispatched');
submit('utr_wa_save_global', array('enhance_links'=>1, 'message'=>'Default {utm_source}', 'source_fields'=>array('utm_source','session_id'), 'fallback_enabled'=>0, 'wa_source_mode'=>'session'), 'global_saved');
$saved = utr_whatsapp_settings();
check($saved['greeting'] === "Halo baru!\nPilih admin." && $saved['floating_enabled'] === 1, 'Message save reset appearance');
check($saved['message'] === 'Default {utm_source}' && utr_wa_source_mode() === 'session', 'Default message/source mode not saved');
$before = $saved;
submit('utr_wa_save_global', array('message'=>'Should not save', 'fallback_enabled'=>1, 'fallback_url_mode'=>'custom', 'fallback_custom_url'=>'invalid'), 'invalid_fallback_url');
check(utr_whatsapp_settings() === $before, 'Invalid URL partially saved settings');
submit('utr_wa_save_global', array('greeting'=>"Halo baru!\nPilih admin.", 'floating_enabled'=>1, 'floating_position'=>'right', 'message'=>'Default kanan', 'fallback_enabled'=>0), 'global_saved');
check(utr_whatsapp_settings()['floating_position'] === 'right', 'Main save did not switch position to right');
submit('utr_wa_save_global', array('save_section'=>'appearance', 'greeting'=>"Halo baru!\nPilih admin.", 'floating_enabled'=>1, 'floating_position'=>'left', 'fallback_enabled'=>1, 'fallback_url_mode'=>'custom', 'fallback_custom_url'=>'invalid'), 'appearance_saved');
check(utr_whatsapp_settings()['floating_position'] === 'left', 'Appearance save did not switch position to left');
check(utr_whatsapp_settings()['message'] === 'Default kanan', 'Appearance button changed default message');
$options['utr_wa_accounts'] = array(1=>array_merge(utr_wa_account_defaults(), array('id'=>1, 'name'=>'Admin', 'phone'=>'628123456789')));
ob_start(); utr_wa_render_floating_accounts(); $html=ob_get_clean();
check(str_contains($html, '<strong>Konsultasi</strong>'), 'New panel title not rendered');
check(str_contains($html, 'utr-wa-widget-left'), 'Saved position not rendered');
check(str_contains($html, 'Halo baru!<br'), 'New multiline greeting not rendered');
check(!str_contains($html, 'Halo! Pilih admin yang ingin Anda hubungi.'), 'Old greeting still rendered');
ob_start(); utr_render_header_version(); $version=ob_get_clean();
check(str_contains($version, UTR_VERSION), 'Header version missing');
check(in_array('utr_wa_save_appearance', $nonces, true) && in_array('utr_wa_save_global', $nonces, true), 'Save handlers skipped nonce checks');
echo "PHP admin save/render tests: PASS\n";
