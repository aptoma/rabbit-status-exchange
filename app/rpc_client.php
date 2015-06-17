<?php
require_once __DIR__ . '/../vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;


class PackageTemplateClient {
    private $connection;
    private $channel;
    private $callback_queue;
    private $response;
    private $corr_id;

    public function __construct() {
        $this->connection = new AMQPConnection(
            'localhost', 5672, 'guest', 'guest');
        $this->channel = $this->connection->channel();
        list($this->callback_queue, ,) = $this->channel->queue_declare(
            "", false, false, true, false);
        $this->channel->basic_consume(
            $this->callback_queue, '', false, false, false, false,
            array($this, 'on_response'));
    }
    public function on_response($rep) {
        if($rep->get('correlation_id') == $this->corr_id) {
            $this->response = $rep->body;
        }
    }

    public function call($body) {
        $this->response = null;
        $this->corr_id = uniqid();

        $msg = new AMQPMessage(
            $body,
            array('correlation_id' => $this->corr_id,
                  'reply_to' => $this->callback_queue)
            );
        $this->channel->basic_publish($msg, '', 'rpc_queue');
        while(!$this->response) {
            $this->channel->wait();
        }
        return $this->response;
    }
};


$request = new stdClass();
$request->action = 'packageTemplateList';
$request->method = 'GET';
$request->accept = 'application/xml';
$request->productId = '123';
$request->sectionId = '456';
$template_rpc = new PackageTemplateClient();
$response = $template_rpc->call(json_encode($request));
echo " [.] Got ", $response, "\n";
