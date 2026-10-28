<?php
namespace UtrWebhook;

/** Plain PHP/MySQL adapter; other stacks can implement the same Store contract. */
class PdoStore implements Store {
    private $db;
    public function __construct(\PDO $db) {
        $this->db = $db;
        $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
    }
    public function findSite($siteId) {
        $query = $this->db->prepare('SELECT site_id, home_url, token_hash, enabled FROM utr_integration_sites WHERE site_id = ?');
        $query->execute(array($siteId));
        return $query->fetch(\PDO::FETCH_ASSOC);
    }
    public function health() { $this->db->query('SELECT event_id FROM utr_wa_clicks LIMIT 1'); }
    public function saveBatch($siteId, array $events, $receivedUtc) {
        if (!$this->db->beginTransaction()) throw new \RuntimeException('Transaction unavailable');
        try {
            foreach ($events as $event) {
                $row = array_merge(array('site_id'=>$siteId), $event, array('received_at_utc'=>$receivedUtc));
                $sql = 'INSERT INTO utr_wa_clicks (`' . implode('`,`', array_keys($row)) . '`) VALUES (' . implode(',', array_fill(0,count($row),'?')) . ') ON DUPLICATE KEY UPDATE event_id = event_id';
                $query = $this->db->prepare($sql);
                if (!$query->execute(array_values($row))) throw new \RuntimeException('Insert failed');
            }
            if (!$this->db->commit()) throw new \RuntimeException('Commit failed');
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}
