<?php
defined('BASEPATH') OR exit('No direct script access allowed');
// Install in application/controllers/Utr_webhook.php.
require_once APPPATH . 'libraries/utr_webhook/UtrReceiver.php';
require_once APPPATH . 'libraries/utr_webhook/SqlStore.php';

class Utr_webhook extends CI_Controller {
    public function clicks() {
        try {
            $this->load->database();
            $receiver = new \UtrWebhook\Receiver(new \UtrWebhook\SqlStore($this->db));
            $result = $receiver->handle($this->input->method(true), $this->input->get_request_header('Authorization', false), $this->input->raw_input_stream);
        } catch (\Throwable $e) { $result = array('status'=>503,'body'=>array('ok'=>false,'error_code'=>'database_error')); }
        return $this->output->set_status_header($result['status'])->set_content_type('application/json','utf-8')->set_header('Cache-Control: no-store')->set_output(json_encode($result['body']));
    }
}
