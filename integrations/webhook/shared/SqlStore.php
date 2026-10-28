<?php
namespace UtrWebhook;

/** Uses the application's existing CodeIgniter connection and table prefix. */
class SqlStore implements Store {
    private $db;
    private $ci4;
    private $sites;
    private $clicks;
    public function __construct($db, $ci4 = false) {
        $this->db = $db; $this->ci4 = $ci4;
        $this->sites = $ci4 ? $db->prefixTable('utr_integration_sites') : $db->dbprefix('utr_integration_sites');
        $this->clicks = $ci4 ? $db->prefixTable('utr_wa_clicks') : $db->dbprefix('utr_wa_clicks');
        foreach (array($this->sites, $this->clicks) as $table) {
            if (!preg_match('/\A[A-Za-z0-9_]+\z/D', $table)) throw new \RuntimeException('Unsupported table prefix');
        }
    }
    private function query($sql, array $params = array()) {
        $query = $this->db->query($sql, $params);
        if (false === $query) throw new \RuntimeException('Database operation failed');
        return $query;
    }
    public function findSite($siteId) {
        $query = $this->query("SELECT site_id, home_url, token_hash, enabled FROM `{$this->sites}` WHERE site_id = ?", array($siteId));
        return $this->ci4 ? $query->getRowArray() : $query->row_array();
    }
    public function health() { $this->query("SELECT event_id FROM `{$this->clicks}` LIMIT 1"); }
    public function saveBatch($siteId, array $events, $receivedUtc) {
        $started = $this->ci4 ? $this->db->transBegin() : $this->db->trans_begin();
        if (false === $started) throw new \RuntimeException('Transaction unavailable');
        try {
            foreach ($events as $event) {
                $row = array_merge(array('site_id'=>$siteId), $event, array('received_at_utc'=>$receivedUtc));
                $columns = array_keys($row);
                $sql = "INSERT INTO `{$this->clicks}` (`" . implode('`,`', $columns) . '`) VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ') ON DUPLICATE KEY UPDATE event_id = event_id';
                $this->query($sql, array_values($row));
            }
            $valid = $this->ci4 ? $this->db->transStatus() : $this->db->trans_status();
            if (!$valid) throw new \RuntimeException('Transaction failed');
            $committed = $this->ci4 ? $this->db->transCommit() : $this->db->trans_commit();
            if (false === $committed) throw new \RuntimeException('Commit failed');
        } catch (\Throwable $e) {
            if ($this->ci4) $this->db->transRollback(); else $this->db->trans_rollback();
            throw $e;
        }
    }
}
