<?php
// Plain PHP endpoint. Set environment variables in the server configuration;
// never store real database credentials in this distributed file.
require_once dirname(__DIR__) . '/shared/UtrReceiver.php';
require_once dirname(__DIR__) . '/shared/PdoStore.php';
try {
    $dsn = getenv('UTR_DB_DSN');
    if (!$dsn || strpos($dsn, 'mysql:') !== 0) throw new RuntimeException('Configure MySQL DSN');
    $db = new PDO($dsn, getenv('UTR_DB_USER') ?: '', getenv('UTR_DB_PASSWORD') ?: '');
    $receiver = new \UtrWebhook\Receiver(new \UtrWebhook\PdoStore($db));
    $result = $receiver->handle($_SERVER['REQUEST_METHOD'] ?? '', $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '', file_get_contents('php://input', false, null, 0, 450001));
} catch (Throwable $e) { $result = array('status'=>503,'body'=>array('ok'=>false,'error_code'=>'database_error')); }
http_response_code($result['status']);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode($result['body']);
