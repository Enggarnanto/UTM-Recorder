<?php
/** Isolated WordPress/HTTP contract tests; no live credentials or requests. */
define( 'ABSPATH', __DIR__ . '/' );
define( 'ARRAY_A', 'ARRAY_A' );
$options = array();
$scheduled = array();
$calls = array();
$response_mode = 'ok';
$get_calls = array();
$redirect_body = array();
$allowed = true;
$nonce_checks = 0;
class WP_Error {
	private $message;
	function __construct( $code, $message ) { $this->message = $message; }
	function get_error_message() { return $this->message; }
}
class Redirect extends RuntimeException {}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function add_action() {}
function add_filter() {}
function get_option( $name, $default = false ) { global $options; return $options[$name] ?? $default; }
function update_option( $name, $value, $autoload = false ) { global $options; $options[$name] = $value; }
function delete_option( $name ) { global $options; unset($options[$name]); }
function add_option( $name, $value, $deprecated = '', $autoload = false ) { global $options; if (isset($options[$name])) return false; $options[$name]=$value; return true; }
function wp_cache_delete() {}
function wp_generate_uuid4() { return uniqid('test-', true); }
function wp_next_scheduled( $name ) { global $scheduled; return $scheduled[$name] ?? false; }
function wp_schedule_event( $time, $interval, $name ) { global $scheduled; $scheduled[$name]=$time; }
function wp_clear_scheduled_hook( $name ) { global $scheduled; unset($scheduled[$name]); }
function wp_json_encode( $value ) { return json_encode($value); }
function home_url() { return 'https://website-a.example/'; }
function utr_wa_click_table() { return 'wp_utm_traffic_wa_clicks'; }
function wp_remote_retrieve_body( $response ) { return $response['body']; }
function wp_remote_retrieve_response_code( $response ) { return $response['code']; }
function wp_remote_retrieve_header( $response, $name ) { return $response['headers'][$name] ?? ''; }
function wp_safe_remote_post( $url, $args ) {
	global $calls, $response_mode, $redirect_body;
	$body = json_decode($args['body'], true);
	$calls[] = array($url, $args, $body);
	check($args['redirection']===0,'Automatic POST redirect enabled');
	if (0 === strpos($response_mode,'redirect')) {
		$redirect_body=$body;
		$location='https://script.googleusercontent.com/macros/echo?user_content_key=test';
		if ('redirect-login' === $response_mode) $location='https://accounts.google.com/signin';
		if ('redirect-unsafe' === $response_mode) $location='https://script.googleusercontent.com.evil.example/echo';
		if ('redirect-userinfo' === $response_mode) $location='https://user:password@script.googleusercontent.com/echo';
		return array('code'=>302,'body'=>'Moved','headers'=>array('location'=>$location));
	}
	if ('network' === $response_mode) return new WP_Error('network', 'secret internal diagnostic');
	if ('html' === $response_mode) return array('code'=>200, 'body'=>'<html>Google login</html>');
	if ('unauthorized' === $response_mode) return array('code'=>200, 'body'=>'{"ok":false,"error":"Unauthorized"}');
	if (isset($body['action'])) return array('code'=>200, 'body'=>json_encode(array('ok'=>true,'site_id'=>$body['site_id'])));
	$ids = array_map('strval', array_column($body['events'], 'id'));
	if ('partial' === $response_mode) array_pop($ids);
	return array('code'=>200, 'body'=>json_encode(array('ok'=>true, 'site_id'=>'wrong-site' === $response_mode ? 'web_b' : $body['site_id'], 'accepted_ids'=>$ids, 'inserted'=>count($ids))));
}
function wp_safe_remote_get( $url, $args ) {
	global $get_calls, $response_mode, $redirect_body;
	check(parse_url($url,PHP_URL_HOST)==='script.googleusercontent.com','Unsafe GET redirect host');
	check(!isset($args['body']) && !isset($args['headers']['Content-Type']) && !isset($args['headers']['Authorization']),'POST body or credentials forwarded to GET');
	check($args['redirection']===0 && $args['sslverify']===true,'GET safety settings missing');
	$get_calls[]=array($url,$args);
	if ('redirect-down' === $response_mode || ('redirect-flaky' === $response_mode && count($get_calls)===1)) return array('code'=>404,'body'=>'Not available yet');
	$body=$redirect_body;
	return array('code'=>200,'body'=>json_encode(array('ok'=>true,'site_id'=>$body['site_id'],'accepted_ids'=>array_map('strval',array_column($body['events']??array(),'id')),'inserted'=>count($body['events']??array()))));
}
function current_user_can() { global $allowed; return $allowed; }
function check_admin_referer() { global $nonce_checks; ++$nonce_checks; }
function wp_die( $message ) { throw new RuntimeException($message); }
function wp_unslash( $value ) { return stripslashes($value); }
function sanitize_key( $value ) { return preg_replace('/[^a-z0-9_-]/','',strtolower($value)); }
function wp_safe_redirect( $url ) { throw new Redirect($url); }
function admin_url( $path ) { return 'https://website-a.example/wp-admin/' . $path; }
function esc_html( $value ) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function esc_attr( $value ) { return esc_html($value); }
function esc_url( $value ) { return esc_html($value); }
function checked( $value ) { if ($value) echo 'checked'; }
function wp_nonce_field() { echo '<input type="hidden" name="_wpnonce" value="test">'; }
function number_format_i18n( $value ) { return number_format($value); }
function wp_date( $format, $time ) { return gmdate($format, $time); }
class SheetsDb {
	public $options = 'wp_options';
	public $last_error = '';
	public $rows = array();
	public $fail = false;
	function prepare( $sql, ...$args ) { return array('sql'=>$sql,'args'=>$args); }
	function query( $query ) { global $options; if (($options['utr_sheets_lock'] ?? null) === $query['args'][1]) unset($options['utr_sheets_lock']); }
	function get_results( $query, $format ) {
		if ($this->fail) { $this->last_error='db error'; return null; }
		return array_slice(array_values(array_filter($this->rows, function($row) use ($query) { return $row['id'] > $query['args'][0]; })),0,100);
	}
	function get_var( $query ) { return count(array_filter($this->rows,function($row) use ($query) { return $row['id'] > $query['args'][0]; })); }
}
$wpdb = new SheetsDb();
require dirname(__DIR__) . '/utm-traffic-recorder/includes/google-sheets.php';
function check( $ok, $message ) { if (!$ok) throw new RuntimeException($message); }
function admin_submit( $post ) { $_POST=$post; try { utr_sheets_admin_action(); } catch (Redirect $e) { return; } throw new RuntimeException('Missing redirect'); }
$config = array('enabled'=>1, 'endpoint'=>'https://script.google.com/macros/s/test-deployment/exec','site_id'=>'web_a','token'=>str_repeat('secret',8));
$options['utr_sheets_config']=$config;
foreach (array('http://script.google.com/macros/s/x/exec','https://script.google.com.evil.test/macros/s/x/exec','https://localhost/exec','https://script.google.com/macros/s/x/dev','https://script.google.com/macros/s/x/exec?token=bad') as $url) check(!utr_sheets_endpoint_valid($url),'Unsafe endpoint allowed');
utr_sheets_schedule(); check(isset($scheduled['utr_sheets_sync']),'Cron not scheduled');
for($i=1;$i<=101;++$i) $wpdb->rows[]=array('id'=>$i,'occurred_at'=>'2026-10-28 01:00:00','phone'=>'628123456789','utm_source'=>'google','message_source'=>'Sumber: google');
check(!is_wp_error(utr_sheets_sync()),'First batch failed');
check($options['utr_sheets_cursor']===100,'Batch cursor should be 100');
check(count($calls[0][2]['events'])===100 && $calls[0][2]['token']===$config['token'],'Payload contract mismatch');
check($calls[0][1]['sslverify']===true && !isset($options['utr_sheets_lock']),'TLS or lock cleanup failed');
foreach(array('network','html','unauthorized','partial','wrong-site','redirect-down','redirect-login','redirect-unsafe','redirect-userinfo') as $mode) {
	$response_mode=$mode;
	check(is_wp_error(utr_sheets_sync()),'Failure not detected: '.$mode);
	check($options['utr_sheets_cursor']===100,'Failure advanced cursor: '.$mode);
}
$response_mode='redirect-ok';
check(!is_wp_error(utr_sheets_sync()),'ContentService redirect not acknowledged');
check($options['utr_sheets_cursor']===101,'Redirect confirmation did not advance cursor');
check(count(end($calls)[2]['events'])===1,'Original POST unexpectedly replayed');
foreach(array('redirect-login','redirect-unsafe','redirect-userinfo') as $mode) {
	$response_mode=$mode; $get_calls=array();
	check(is_wp_error(utr_sheets_request($config,array('action'=>'test'))),'Unsafe redirect accepted');
	check(count($get_calls)===0,'Unsafe redirect fetched');
}
$response_mode='redirect-flaky'; $get_calls=array(); $before=count($calls);
check(!is_wp_error(utr_sheets_request($config,array('action'=>'test'))),'Transient response 404 did not recover');
check(count($get_calls)===2 && count($calls)===$before+1,'Retry repeated POST rather than reading response');
$response_mode='ok';
utr_sheets_sync(); check($options['utr_sheets_cursor']===101,'Retry skipped last click');
$before=count($calls); utr_sheets_sync(); check(count($calls)===$before,'Empty queue sent a request');
utr_sheets_sync(true); check($options['utr_sheets_cursor']===100,'Replay did not restart history');
$wpdb->fail=true; check(is_wp_error(utr_sheets_sync()),'DB failure reported success'); $wpdb->fail=false;
$options['utr_sheets_lock']=(time()+100).':another-run';
check(is_wp_error(utr_sheets_sync()),'Concurrent sync was allowed');
$options['utr_sheets_lock']=(time()-1).':expired-run';
check(!is_wp_error(utr_sheets_sync()),'Expired lock did not recover');
admin_submit(array('operation'=>'save','endpoint'=>$config['endpoint'],'site_id'=>'web_a','token'=>'','enabled'=>'1'));
check($options['utr_sheets_config']['token']===$config['token'],'Blank password erased saved key');
admin_submit(array('operation'=>'save','endpoint'=>'https://evil.example/exec','site_id'=>'web_a','enabled'=>'1'));
check($options['utr_sheets_config']===$config,'Invalid configuration partially saved');
admin_submit(array('operation'=>'save','endpoint'=>$config['endpoint'],'site_id'=>'web_b','enabled'=>'1'));
check($options['utr_sheets_cursor']===0,'Changing destination did not reset cursor');
admin_submit(array('operation'=>'test'));
$last=end($calls); check($last[2]['action']==='test' && !isset($last[2]['events']),'Connection test wrote clicks');
ob_start(); utr_render_sheets_settings(); $html=ob_get_clean();
check(strpos($html,$config['token'])===false,'Secret rendered in HTML');
check(strpos($html,'Sinkronkan sekarang')!==false && strpos($html,'Tes koneksi')!==false,'Missing integration controls');
if (isset($argv[1])) {
	$css = file_get_contents(dirname(__DIR__) . '/utm-traffic-recorder/assets/admin.css');
	file_put_contents($argv[1], '<!doctype html><html lang="id"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:24px;background:#f0f0f1;font-family:Arial,sans-serif}.button{display:inline-block;padding:8px 12px;border:1px solid #2271b1;border-radius:4px;background:white;color:#2271b1}.button-primary{background:#2271b1;color:white}.regular-text{width:25em}.large-text{width:100%}.notice{padding:1px 12px;border-left:4px solid #00a32a;background:white}input{box-sizing:border-box;border:1px solid #8c8f94;border-radius:4px}input[type=checkbox]{width:16px;height:16px}' . $css . '</style><div class="wrap utr-admin">' . $html . '</div></html>');
}
admin_submit(array('operation'=>'save','endpoint'=>$config['endpoint'],'site_id'=>'web_b','token'=>''));
check(!isset($scheduled['utr_sheets_sync']),'Disabling integration left cron scheduled');
$before=count($calls); utr_sheets_sync(); check(count($calls)===$before,'Disabled integration sent data');
$allowed=false; try { admin_submit(array('operation'=>'test')); throw new RuntimeException('Unauthorized admin allowed'); } catch (RuntimeException $e) { check(strpos($e->getMessage(),'izin')!==false,'Permission check failed'); }
check($nonce_checks===5,'Admin actions skipped nonce verification');
echo "Google Sheets PHP sync/admin tests: PASS\n";
