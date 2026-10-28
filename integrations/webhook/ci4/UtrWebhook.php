<?php
namespace App\Controllers;

// Install in app/Controllers/UtrWebhook.php. Intentionally does not inherit
// the application's login-only BaseController: API authentication is Bearer.
require_once APPPATH . 'Libraries/UtrWebhook/UtrReceiver.php';
require_once APPPATH . 'Libraries/UtrWebhook/SqlStore.php';

class UtrWebhook extends \CodeIgniter\Controller {
    public function clicks() {
        try {
            $receiver = new \UtrWebhook\Receiver(new \UtrWebhook\SqlStore(db_connect(), true));
            $result = $receiver->handle($this->request->getMethod(), $this->request->getHeaderLine('Authorization'), $this->request->getBody());
        } catch (\Throwable $e) { $result = array('status'=>503,'body'=>array('ok'=>false,'error_code'=>'database_error')); }
        return $this->response->setStatusCode($result['status'])->setHeader('Cache-Control','no-store')->setJSON($result['body']);
    }
}
