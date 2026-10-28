<?php
/** End-to-end client -> receiver -> storage contract, without live credentials. */
define('ABSPATH',__DIR__.'/');define('ARRAY_A','ARRAY_A');
$options=array();$scheduled=array();$calls=array();$allowed=true;$nonce_checks=0;$mode='ok';
class WP_Error{private $message;function __construct($code,$message){$this->message=$message;}function get_error_message(){return $this->message;}}
class Redirect extends RuntimeException{}
function is_wp_error($v){return $v instanceof WP_Error;}
function add_action(){}function add_filter(){}
function get_option($n,$d=false){global $options;return $options[$n]??$d;}
function update_option($n,$v,$a=false){global $options;$options[$n]=$v;}
function add_option($n,$v,$d='',$a=false){global $options;if(isset($options[$n]))return false;$options[$n]=$v;return true;}
function wp_cache_delete(){}function wp_generate_uuid4(){return uniqid('test-',true);}
function wp_next_scheduled($n){global $scheduled;return $scheduled[$n]??false;}
function wp_schedule_event($t,$i,$n){global $scheduled;$scheduled[$n]=$t;}
function wp_clear_scheduled_hook($n){global $scheduled;unset($scheduled[$n]);}
function wp_json_encode($v){return json_encode($v);}
function home_url(){return 'https://website-a.example/';}
function utr_wa_click_table(){return 'wp_clicks';}
function wp_remote_retrieve_response_code($r){return $r['code'];}
function wp_remote_retrieve_body($r){return $r['body'];}
function current_user_can(){global $allowed;return $allowed;}
function check_admin_referer(){global $nonce_checks;++$nonce_checks;}
function wp_die($m){throw new RuntimeException($m);}
function wp_unslash($v){return stripslashes($v);}function sanitize_key($v){return preg_replace('/[^a-z0-9_-]/','',strtolower($v));}
function wp_safe_redirect($v){throw new Redirect($v);}function admin_url($p){return 'https://website-a.example/wp-admin/'.$p;}
function esc_html($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}function esc_attr($v){return esc_html($v);}function esc_url($v){return esc_html($v);}
function checked($v){if($v)echo 'checked';}function wp_nonce_field(){echo '<input type="hidden" name="_wpnonce" value="test">';}
function number_format_i18n($v){return number_format($v);}function wp_date($f,$t){return gmdate($f,$t);}
require dirname(__DIR__).'/integrations/webhook/shared/UtrReceiver.php';
class MemoryStore implements \UtrWebhook\Store{
 public $rows=array(); public $fail=false; public $sites=array();
 function findSite($id){return $this->sites[$id]??null;}
 function health(){if($this->fail)throw new RuntimeException('database secret');}
 function saveBatch($site,array $events,$now){if($this->fail)throw new RuntimeException('Commit failed');foreach($events as$event){$key=$site.':'.$event['event_id'];if(!isset($this->rows[$key]))$this->rows[$key]=$event;}}
}
$store=new MemoryStore();$receiver=new \UtrWebhook\Receiver($store);
$token=str_repeat('secret',8);$store->sites['website_a']=array('enabled'=>1,'home_url'=>home_url(),'token_hash'=>hash('sha256',$token));
$store->sites['website_b']=array('enabled'=>1,'home_url'=>home_url(),'token_hash'=>hash('sha256',$token));
function wp_safe_remote_post($url,$args){
 global $calls,$mode,$receiver;$calls[]=array($url,$args);
 check($args['redirection']===0&&$args['sslverify']===true,'Transport not safe');
 $payload=json_decode($args['body'],true);check(!isset($payload['token']),'Secret leaked into payload');
 if($mode==='network')return new WP_Error('network','internal diagnostic');
 if($mode==='redirect')return array('code'=>302,'body'=>'login');
 if($mode==='missing')return array('code'=>404,'body'=>'Not found');
 if($mode==='html')return array('code'=>200,'body'=>'<html>Login</html>');
 $result=$receiver->handle('POST',$args['headers']['Authorization'],$args['body']);
 if($mode==='partial'&&!empty($result['body']['accepted_ids']))array_pop($result['body']['accepted_ids']);
 if($mode==='wrong-site')$result['body']['site_id']='wrong';
 return array('code'=>$result['status'],'body'=>json_encode($result['body']));
}
class MockDb{
 public $options='wp_options';public $rows=array();public $last_error='';public $fail=false;
 function prepare($s,...$a){return array('sql'=>$s,'args'=>$a);}
 function query($q){global $options;if(($options[$q['args'][0]]??null)===$q['args'][1])unset($options[$q['args'][0]]);}
 function get_results($q,$format){if($this->fail){$this->last_error='fail';return null;}return array_slice(array_values(array_filter($this->rows,function($r)use($q){return $r['id']>$q['args'][0];})),0,100);}
 function get_var($q){return count(array_filter($this->rows,function($r)use($q){return $r['id']>$q['args'][0];}));}
}
$wpdb=new MockDb();
require dirname(__DIR__).'/utm-traffic-recorder/includes/google-sheets.php';
require dirname(__DIR__).'/utm-traffic-recorder/includes/webhook-integration.php';
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
function submit($post){$_POST=$post;try{utr_webhook_admin_action();}catch(Redirect $e){return;}throw new RuntimeException('Missing redirect');}
$config=array('enabled'=>1,'endpoint'=>'https://api.example.com/api/integrations/utr/wa-clicks','site_id'=>'website_a','token'=>$token);$options['utr_webhook_config']=$config;
$options['utr_sheets_cursor']=900;$options['utr_sheets_lock']=(time()+100).':sheets-worker';
for($i=1;$i<=101;++$i)$wpdb->rows[]=array('id'=>(string)$i,'occurred_at'=>'2026-10-28 14:00:00','phone'=>'628123456789','utm_source'=>'google','link_type'=>'existing');
utr_webhook_schedule();check(isset($scheduled['utr_webhook_sync']),'Cron missing');
check(!is_wp_error(utr_webhook_request($config))&&count($store->rows)===0,'Test inserted data');
check(!is_wp_error(utr_webhook_sync()),'First batch failed');check($options['utr_webhook_cursor']===100&&count($store->rows)===100,'First batch mismatch');
check($options['utr_sheets_cursor']===900&&isset($options['utr_sheets_lock']),'Sheets state was modified');
foreach(array('network','redirect','missing','html','partial','wrong-site')as$failure){$mode=$failure;check(is_wp_error(utr_webhook_sync()),'Failure accepted: '.$failure);check($options['utr_webhook_cursor']===100,'Failure skipped records');}
$mode='ok';check(!is_wp_error(utr_webhook_sync())&&$options['utr_webhook_cursor']===101,'Retry did not recover');check(count($store->rows)===101,'Retry duplicated records');
utr_webhook_sync(true);check($options['utr_webhook_cursor']===100&&count($store->rows)===101,'Replay duplicated records');
$store->fail=true;check(is_wp_error(utr_webhook_sync())&&$options['utr_webhook_cursor']===100,'Failed database commit advanced cursor');$store->fail=false;
$wpdb->fail=true;check(is_wp_error(utr_webhook_sync()),'Local DB failure not detected');$wpdb->fail=false;
$options['utr_webhook_lock']=(time()+100).':other';check(is_wp_error(utr_webhook_sync()),'Concurrent worker allowed');unset($options['utr_webhook_lock']);
submit(array('operation'=>'save','endpoint'=>$config['endpoint'],'site_id'=>'website_a','token'=>'','enabled'=>1));check($options['utr_webhook_config']===$config,'Blank key lost credentials');
submit(array('operation'=>'save','endpoint'=>'http://unsafe.example/api','site_id'=>'website_a','enabled'=>1));check($options['utr_webhook_config']===$config,'Invalid settings partially saved');
submit(array('operation'=>'save','endpoint'=>$config['endpoint'],'site_id'=>'website_b','enabled'=>1));check($options['utr_webhook_cursor']===0,'New destination did not reset cursor');
submit(array('operation'=>'test'));check(count($store->rows)===101,'Admin test inserted events');
ob_start();utr_render_webhook_settings();$html=ob_get_clean();check(strpos($html,$token)===false,'HTML leaked API key');check(stripos($html,'latis')===false,'Company-specific UI');
if(isset($argv[1]))file_put_contents($argv[1],$html);
submit(array('operation'=>'save','endpoint'=>$config['endpoint'],'site_id'=>'website_b','token'=>''));check(!isset($scheduled['utr_webhook_sync']),'Disabled cron still scheduled');
$before=count($calls);utr_webhook_sync();check(count($calls)===$before,'Disabled integration sent events');
$base=array('protocol_version'=>1,'site_id'=>'website_a','site_url'=>home_url(),'action'=>'sync','events'=>array($wpdb->rows[0]));
$auth='Bearer '.$token;
check($receiver->handle('POST','Bearer '.str_repeat('wrong',10),json_encode($base))['status']===401,'Invalid key accepted');
$bad=$base;$bad['site_url']='https://other.example/';check($receiver->handle('POST',$auth,json_encode($bad))['body']['error_code']==='site_url_mismatch','Site URL binding ignored');
foreach(array('2026-02-30 01:00:00','not-a-date')as$time){$bad=$base;$bad['events'][0]['occurred_at']=$time;check($receiver->handle('POST',$auth,json_encode($bad))['status']===400,'Invalid UTC timestamp accepted');}
$bad=$base;$bad['events'][0]['id']='18446744073709551616';check($receiver->handle('POST',$auth,json_encode($bad))['status']===400,'Unsigned BIGINT overflow accepted');
$bad=$base;$bad['events'][]=$bad['events'][0];check($receiver->handle('POST',$auth,json_encode($bad))['status']===400,'Duplicate batch IDs accepted');
$bad=$base;$bad['events'][0]['utm_source']=array('bad');check($receiver->handle('POST',$auth,json_encode($bad))['status']===400,'Object field accepted');
$base['site_id']='website_b';check($receiver->handle('POST',$auth,json_encode($base))['status']===200&&count($store->rows)===102,'Websites collided');
$allowed=false;try{submit(array('operation'=>'test'));throw new RuntimeException('Unauthorized admin allowed');}catch(RuntimeException $e){check(strpos($e->getMessage(),'izin')!==false,'Permission check failed');}
check($nonce_checks===5,'Admin nonce checks missing');
echo "Generic webhook client/receiver, independent queues, auth, retries, deduplication: PASS\n";
