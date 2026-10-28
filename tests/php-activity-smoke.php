<?php
define('ABSPATH',__DIR__.'/');define('ARRAY_A','ARRAY_A');define('DAY_IN_SECONDS',86400);
$options=array('utr_sheets_config'=>array('token'=>str_repeat('google-secret-',4)),'utr_webhook_config'=>array('token'=>str_repeat('webhook-secret-',4)));
$allowed=true;$schema='';
function add_action(){}function add_filter(){}
function wp_json_encode($v){return json_encode($v);}
function get_option($n,$d=false){global $options;return $options[$n]??$d;}
function get_current_user_id(){return 7;}
function current_user_can(){global $allowed;return $allowed;}
function wp_die($m){throw new RuntimeException($m);}
function dbDelta($sql){global $schema;$schema=$sql;}
function sanitize_key($v){return preg_replace('/[^a-z0-9_-]/','',strtolower($v));}
function wp_unslash($v){return stripslashes($v);}function absint($v){return abs((int)$v);}
function esc_html($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}function esc_attr($v){return esc_html($v);}function esc_url($v){return esc_html($v);}
function admin_url($p){return 'https://example.test/wp-admin/'.$p;}function add_query_arg($a,$u){return $u.'?'.http_build_query($a);}function wp_nonce_url($u,$a){return $u.'&_wpnonce=test';}
function selected($a,$b){if($a===$b)echo 'selected';}function number_format_i18n($v){return number_format($v);}function wp_date($f,$t){return gmdate($f,$t);}
function utr_render_header_version(){echo '<span>2.6.16</span>';}function utr_render_brand_footer(){}
class LogDb{
 public $prefix='wp_';public $rows=array();public $queries=array();public $suppressed=false;public $fail=false;
 function get_charset_collate(){return 'DEFAULT CHARSET=utf8mb4';}
 function suppress_errors($v){$old=$this->suppressed;$this->suppressed=$v;return $old;}
 function insert($table,$row){if($this->fail)throw new RuntimeException('Insert failed');$row['id']=count($this->rows)+1;$this->rows[]=$row;return true;}
 function prepare($sql,...$args){if(count($args)===1&&is_array($args[0]))$args=$args[0];foreach($args as$v)$sql=preg_replace('/%[sd]/',is_int($v)?(string)$v:"'".str_replace("'","''",$v)."'",$sql,1);return $sql;}
 function query($sql){$this->queries[]=$sql;}
 function get_var($sql){return strpos($sql,'COUNT')!==false?count($this->rows):123;}
 function get_results($sql){return array_map(function($r){return(object)$r;},$this->rows);}
}
$wpdb=new LogDb();require dirname(__DIR__).'/utm-traffic-recorder/includes/activity-log.php';
function check($ok,$m){if(!$ok)throw new RuntimeException($m);}
utr_install_activity_table();check(strpos($schema,'wp_utm_activity_log')!==false&&strpos($schema,'PRIMARY KEY')!==false,'Audit schema missing');
$secret=$options['utr_sheets_config']['token'];$webhook=$options['utr_webhook_config']['token'];
utr_activity_log('sheets.operation','error',array('detail'=>'failed '.$secret.' '.$webhook.' Bearer hidden-value token=hidden-password https://script.googleusercontent.com/echo?secret=abc','phone'=>'628123456789','Authorization'=>'Bearer unsafe','body'=>array('password'=>'unsafe'),'count'=>4));
$row=end($wpdb->rows);$json=$row['context'];
foreach(array($secret,$webhook,'hidden-value','hidden-password','628123456789','https://script.googleusercontent.com','unsafe')as$value)check(strpos($json,$value)===false,'Sensitive audit data leaked: '.$value);
check($row['user_id']===7&&$row['level']==='error'&&$row['category']==='sheets','Audit metadata missing');check(json_decode($json,true)['count']===4,'Safe context missing');
$before=count($wpdb->rows);utr_activity_setting_changed('utr_sheets_config');check(count($wpdb->rows)===$before+1,'Config changes not logged');utr_activity_setting_changed('utr_sheets_lock');check(count($wpdb->rows)===$before+1,'Lock generated audit recursion');
class RequestMock{private $route;function __construct($r){$this->route=$r;}function get_route(){return $this->route;}}
class ResponseMock{private $status;private $data;function __construct($s,$d){$this->status=$s;$this->data=$d;}function get_status(){return $this->status;}function get_data(){return $this->data;}}
foreach(array('/utm-recorder/v1/hit'=>'tracking.pageview','/utm-recorder/v1/wa-click'=>'tracking.wa_click')as$route=>$event){$r=new ResponseMock(201,array('recorded'=>true));check(utr_activity_tracking($r,null,new RequestMock($route))===$r,'REST response modified');check(end($wpdb->rows)['event']===$event,'Tracking success not logged');}
$before=count($wpdb->rows);utr_activity_tracking(new ResponseMock(200,array('recorded'=>false)),null,new RequestMock('/utm-recorder/v1/wa-click'));check(count($wpdb->rows)===$before,'Ignored click reported as recorded');
utr_activity_tracking(new ResponseMock(400,array('token'=>'unsafe')),null,new RequestMock('/utm-recorder/v1/wa-click'));check(end($wpdb->rows)['event']==='tracking.rejected'&&strpos(end($wpdb->rows)['context'],'unsafe')===false,'Tracking failure not safely logged');
$before=count($wpdb->rows);utr_activity_tracking(new ResponseMock(500,array()),null,new RequestMock('/other-plugin/v1/test'));check(count($wpdb->rows)===$before,'Other plugin was audited');
utr_activity_version_changed('utr_loaded_version','2.6.15','2.6.16');check(end($wpdb->rows)['event']==='plugin.updated','Version upgrade missing');
$wpdb->fail=true;check(utr_activity_log('plugin.activated')===false&&$wpdb->suppressed===false,'Logging failure broke primary operation or left errors suppressed');$wpdb->fail=false;
check(utr_activity_log('unknown.event')===false&&utr_activity_log(array())===false,'Unknown audit event accepted');
$_GET=array('utr_level'=>'error','utr_category'=>'sheets');$filters=utr_activity_filters();$where=utr_activity_where($filters);check(strpos($where,"level = 'error'")!==false&&strpos($where,"category = 'sheets'")!==false,'Log filter broken');
$_GET=array('utr_level'=>array('bad'),'utr_category'=>"sheets' OR 1=1");check(utr_activity_filters()===array('level'=>'','category'=>''),'Unsafe filters accepted');
$_GET=array();ob_start();utr_render_activity_log();$html=ob_get_clean();check(strpos($html,'Log aktivitas')!==false&&strpos($html,$secret)===false,'Log page missing or leaked secret');
if(isset($argv[1]))file_put_contents($argv[1],$html);
$allowed=false;try{utr_render_activity_log();throw new RuntimeException('Unauthorized log access');}catch(RuntimeException $e){check(strpos($e->getMessage(),'izin')!==false,'Log permission check failed');}$allowed=true;
utr_activity_cleanup();check(count($wpdb->queries)===2&&strpos($wpdb->queries[0],'occurred_at')!==false&&strpos($wpdb->queries[1],'id <')!==false,'Retention or size cap missing');
echo "Activity logging, privacy, tracking, filters, permissions, retention: PASS\n";
