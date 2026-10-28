<?php
namespace UtrWebhook;

interface Store {
    public function findSite($siteId);
    public function health();
    public function saveBatch($siteId, array $events, $receivedUtc);
}

/** Framework-independent receiver contract. Never exposes exception details. */
class Receiver {
    private $store;
    public function __construct(Store $store) { $this->store = $store; }

    private function result($status, array $body) { return array('status' => $status, 'body' => $body); }
    private function fail($status, $code) { return $this->result($status, array('ok' => false, 'error_code' => $code)); }

    public static function canonicalUrl($url) {
        if (!is_string($url) || preg_match('/[\x00-\x20\x7f]/', $url)) return false;
        $p = parse_url($url);
        if (!is_array($p) || !isset($p['scheme'], $p['host']) || !in_array($p['scheme'], array('http', 'https'), true) || isset($p['user']) || isset($p['pass']) || isset($p['query']) || isset($p['fragment'])) return false;
        $port = isset($p['port']) && !(('https' === $p['scheme'] && 443 === $p['port']) || ('http' === $p['scheme'] && 80 === $p['port'])) ? ':' . $p['port'] : '';
        return $p['scheme'] . '://' . strtolower($p['host']) . $port . rtrim($p['path'] ?? '', '/') . '/';
    }

    private function unsignedId($value, $zero = false) {
        if (!is_int($value) && !is_string($value)) throw new \InvalidArgumentException();
        $id = (string)$value;
        if (!preg_match($zero ? '/\A(?:0|[1-9][0-9]{0,19})\z/D' : '/\A[1-9][0-9]{0,19}\z/D', $id) || (strlen($id) === 20 && strcmp($id, '18446744073709551615') > 0)) throw new \InvalidArgumentException();
        return $id;
    }

    private function event($event) {
        if (!is_array($event)) throw new \InvalidArgumentException();
        $out = array('event_id' => $this->unsignedId($event['id'] ?? null));
        $time = $event['occurred_at'] ?? null;
        if (!is_string($time)) throw new \InvalidArgumentException();
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $time, new \DateTimeZone('UTC'));
        if (!$date || $date->format('Y-m-d H:i:s') !== $time) throw new \InvalidArgumentException();
        $out['occurred_at'] = $time;
        $out['account_id'] = $this->unsignedId($event['account_id'] ?? 0, true);
        $limits = array('visitor_id'=>36, 'session_id'=>36, 'account_name'=>191, 'phone'=>20, 'link_type'=>20, 'page_path'=>2048, 'utm_source'=>191, 'utm_medium'=>191, 'utm_campaign'=>191, 'utm_term'=>191, 'utm_content'=>191, 'utm_match_type'=>191, 'message_source'=>2048);
        foreach ($limits as $field => $max) {
            $value = $event[$field] ?? '';
            if (!is_string($value)) throw new \InvalidArgumentException();
            $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : preg_match_all('/./us', $value);
            if (false === $length || $length > $max || strpos($value, "\0") !== false) throw new \InvalidArgumentException();
            $out[$field] = $value;
        }
        if (!preg_match('/\A[0-9]{0,20}\z/D', $out['phone']) || !in_array($out['link_type'], array('', 'floating', 'shortcode', 'existing'), true)) throw new \InvalidArgumentException();
        $flag = $event['message_source_recorded'] ?? 0;
        if (!in_array($flag, array(0, 1, '0', '1'), true)) throw new \InvalidArgumentException();
        $out['message_source_recorded'] = (int)$flag;
        return $out;
    }

    public function handle($method, $authorization, $rawBody) {
        if (strtoupper($method) !== 'POST') return $this->fail(405, 'method_not_allowed');
        if (!is_string($rawBody) || strlen($rawBody) > 450000) return $this->fail(413, 'payload_too_large');
        $body = json_decode($rawBody, true);
        if (!is_array($body) || ($body['protocol_version'] ?? null) !== 1 || !is_string($body['site_id'] ?? null) || !preg_match('/\A[A-Za-z0-9_-]{1,64}\z/D', $body['site_id'])) return $this->fail(400, 'invalid_batch');
        if (!is_string($authorization) || !preg_match('/\ABearer ([\x21-\x7e]{32,512})\z/D', $authorization, $match)) return $this->fail(401, 'unauthorized');
        try {
            $site = $this->store->findSite($body['site_id']);
            if (!is_array($site) || empty($site['enabled']) || !is_string($site['token_hash'] ?? null) || !hash_equals($site['token_hash'], hash('sha256', $match[1]))) return $this->fail(401, 'unauthorized');
            $url = self::canonicalUrl($body['site_url'] ?? null);
            $registered = self::canonicalUrl($site['home_url'] ?? null);
            if (!$url || !$registered || $url !== $registered) return $this->fail(403, 'site_url_mismatch');
            $action = $body['action'] ?? '';
            if ($action === 'test') {
                $this->store->health();
                return $this->result(200, array('ok'=>true, 'protocol_version'=>1, 'site_id'=>$body['site_id'], 'accepted_ids'=>array(), 'inserted'=>0));
            }
            if ($action !== 'sync' || !is_array($body['events'] ?? null) || count($body['events']) < 1 || count($body['events']) > 100 || array_keys($body['events']) !== range(0, count($body['events']) - 1)) return $this->fail(400, 'invalid_batch');
            $events = array(); $ids = array();
            try {
                foreach ($body['events'] as $item) {
                    $event = $this->event($item);
                    if (in_array($event['event_id'], $ids, true)) return $this->fail(400, 'invalid_batch');
                    $ids[] = $event['event_id']; $events[] = $event;
                }
            } catch (\InvalidArgumentException $e) { return $this->fail(400, 'invalid_batch'); }
            $this->store->saveBatch($body['site_id'], $events, gmdate('Y-m-d H:i:s'));
            // Duplicates are acknowledged too; IDs are returned only after commit.
            return $this->result(200, array('ok'=>true, 'protocol_version'=>1, 'site_id'=>$body['site_id'], 'accepted_ids'=>$ids));
        } catch (\Throwable $e) { return $this->fail(503, 'database_error'); }
    }
}
