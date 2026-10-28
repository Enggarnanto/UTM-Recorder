<?php
require dirname(__DIR__).'/integrations/webhook/shared/UtrReceiver.php';
require dirname(__DIR__).'/integrations/webhook/shared/SqlStore.php';
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
class ResultRows{
 function row_array(){return array('site_id'=>'website_a');}
 function getRowArray(){return $this->row_array();}
}
class FakeCiDb{
 public $rows=array();public $snapshot=array();public $failAt=0;public $writes=0;public $failCommit=false;public $commits=0;public $rollbacks=0;public $methods=array();
 function dbprefix($s){return 'app_'.$s;}function prefixTable($s){return $this->dbprefix($s);}
 function query($sql,$args=array()){
  check(strpos($sql,'app_utr_')!==false,'Database prefix ignored');
  if(strpos($sql,'INSERT')===0){++$this->writes;if($this->failAt===$this->writes)return false;check(strpos($sql,'ON DUPLICATE KEY UPDATE')!==false,'Deduplication clause missing');$key=$args[0].':'.$args[1];if(!isset($this->rows[$key]))$this->rows[$key]=$args;return true;}
  return new ResultRows();
 }
 function begin($name){$this->methods[]=$name;$this->snapshot=$this->rows;$this->writes=0;return true;}
 function commit($name){$this->methods[]=$name;if($this->failCommit)return false;++$this->commits;return true;}
 function rollback($name){$this->methods[]=$name;$this->rows=$this->snapshot;++$this->rollbacks;return true;}
 function trans_begin(){return $this->begin('ci3-begin');}function transBegin(){return $this->begin('ci4-begin');}
 function trans_commit(){return $this->commit('ci3-commit');}function transCommit(){return $this->commit('ci4-commit');}
 function trans_rollback(){return $this->rollback('ci3-rollback');}function transRollback(){return $this->rollback('ci4-rollback');}
 function trans_status(){return true;}function transStatus(){return true;}
}
foreach(array(false,true)as$ci4){
 $db=new FakeCiDb();$store=new \UtrWebhook\SqlStore($db,$ci4);
 check($store->findSite('website_a')['site_id']==='website_a','Site lookup failed');$store->health();
 $events=array(array('event_id'=>'1','occurred_at'=>'2026-10-28 01:00:00'),array('event_id'=>'2','occurred_at'=>'2026-10-28 02:00:00'));
 $store->saveBatch('website_a',$events,'2026-10-28 03:00:00');$store->saveBatch('website_a',$events,'2026-10-28 03:01:00');check(count($db->rows)===2,'Replay duplicated records');
 $store->saveBatch('website_b',$events,'2026-10-28 03:00:00');check(count($db->rows)===4,'Website IDs collided');
 $saved=$db->rows;$db->failAt=2;
 try{$store->saveBatch('website_c',$events,'2026-10-28 03:00:00');throw new RuntimeException('Failed write committed');}catch(RuntimeException $e){check($e->getMessage()==='Database operation failed','Unexpected transaction error');}
 check($db->rows===$saved&&$db->rollbacks===1,'Partial batch not rolled back');$db->failAt=0;$db->failCommit=true;
 try{$store->saveBatch('website_d',$events,'2026-10-28 03:00:00');throw new RuntimeException('Failed commit accepted');}catch(RuntimeException $e){check($e->getMessage()==='Commit failed','Unexpected commit error');}
 check($db->rows===$saved&&$db->rollbacks===2,'Failed commit not rolled back');
 check(in_array($ci4?'ci4-begin':'ci3-begin',$db->methods,true),'Wrong CodeIgniter transaction API');
}
echo "CodeIgniter 3/4 storage prefix/transaction/rollback/deduplication tests: PASS\n";
