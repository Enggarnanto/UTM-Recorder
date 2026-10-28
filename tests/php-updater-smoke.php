<?php
define('ABSPATH', __DIR__.'/'); define('UTR_VERSION','2.6.15'); define('UTR_FILE','/plugins/utm-traffic-recorder/utm-traffic-recorder.php');
define('HOUR_IN_SECONDS',3600); define('MINUTE_IN_SECONDS',60);
$cache=array(); $calls=0; $mode=200;
function add_filter(){} function add_action(){}
function plugin_basename($file){return 'utm-traffic-recorder/utm-traffic-recorder.php';}
function get_site_transient($key){global $cache;return $cache[$key]??false;}
function set_site_transient($key,$value,$ttl){global $cache;$cache[$key]=$value;}
function delete_site_transient($key){global $cache;unset($cache[$key]);}
function is_wp_error($v){return $v instanceof Exception;}
function wp_remote_retrieve_response_code($v){return $v['code'];}
function wp_remote_retrieve_body($v){return $v['body'];}
function esc_html($v){return htmlspecialchars($v,ENT_QUOTES,'UTF-8');}
function fixture(){return array('tag_name'=>'v2.6.16','draft'=>false,'prerelease'=>false,'body'=>'Release notes <script>bad</script>','assets'=>array(array('name'=>'utm-traffic-recorder.zip','state'=>'uploaded','browser_download_url'=>'https://github.com/Enggarnanto/UTM-Recorder/releases/download/v2.6.16/utm-traffic-recorder.zip')));}
function wp_safe_remote_get($url,$args){global $calls,$mode;++$calls;check($url==='https://api.github.com/repos/Enggarnanto/UTM-Recorder/releases/latest','Unexpected API host');return array('code'=>$mode,'body'=>json_encode(fixture()));}
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
require dirname(__DIR__).'/utm-traffic-recorder/includes/github-updater.php';
$update=utr_github_update(false,array(),'other/other.php',array());check($update===false&&$calls===0,'Other plugin triggered updater');
$update=utr_github_update(false,array(),plugin_basename(UTR_FILE),array());
check($update['version']==='2.6.16'&&$update['package']===fixture()['assets'][0]['browser_download_url'],'Native update metadata wrong');
utr_github_update(false,array(),plugin_basename(UTR_FILE),array());check($calls===1,'Release not cached');
foreach(array('draft','prerelease')as$field){$f=fixture();$f[$field]=true;check(isset(utr_github_parse_release($f)['error']),'Unstable release allowed');}
foreach(array('v2.6.16-beta','main','2.6')as$tag){$f=fixture();$f['tag_name']=$tag;check(isset(utr_github_parse_release($f)['error']),'Invalid version allowed');}
foreach(array('http://github.com/Enggarnanto/UTM-Recorder/releases/download/v2.6.16/utm-traffic-recorder.zip','https://evil.example/payload.zip','https://github.com/another/repo/archive/main.zip','https://github.com@evil.example/file.zip','https://github.com/Enggarnanto/UTM-Recorder/releases/download/v2.6.16/utm-traffic-recorder.zip?token=bad')as$url){$f=fixture();$f['assets'][0]['browser_download_url']=$url;check(isset(utr_github_parse_release($f)['error']),'Unsafe asset allowed');}
$f=fixture();$f['assets']=array();check(isset(utr_github_parse_release($f)['error']),'Source-only release allowed');
$f=fixture();$f['assets'][0]['name']='utm-traffic-recorder-2.6.16.zip';$f['assets'][0]['browser_download_url']='https://github.com/Enggarnanto/UTM-Recorder/releases/download/v2.6.16/utm-traffic-recorder-2.6.16.zip';check(utr_github_parse_release($f)['version']==='2.6.16','Versioned ZIP rejected');
$info=utr_github_plugin_info(null,'plugin_information',(object)array('slug'=>'utm-traffic-recorder'));check(strpos($info->sections['changelog'],'<script>')===false,'Release notes not escaped');
foreach(array(404,403,429,500)as$code){$cache=array();$mode=$code;check(isset(utr_github_release()['error']),'Failed API response accepted');}
echo "GitHub updater metadata/cache/security tests: PASS\n";
