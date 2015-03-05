<?php
require_once __DIR__ . '/../vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPConnection;

$connection = new AMQPConnection('localhost', 5672, 'guest', 'guest');
$channel = $connection->channel();
$channel->exchange_declare('articlestatus', 'fanout', false, false, false);
list($queue_name, ,) = $channel->queue_declare("", false, false, true, false);
$channel->queue_bind($queue_name, 'articlestatus');
echo ' [*] Waiting for article status messages.', "\n";
$callback = function ($msg) {
    $properties = $msg->get_properties();
    $messageId = isset($properties['message_id']) ? $properties['message_id'] : null;
    $status = isset($properties['application_headers']['status'][1]) ? $properties['application_headers']['status'][1] : 'unknown';
    $confirmingAppId = isset($properties['app_id']) ? $properties['app_id'] : null;
    echo " [->] {$confirmingAppId} confirmed \n   message id={$messageId} status={$status}\n   body: {$msg->body} \n\n";
};
$channel->basic_consume($queue_name, '', false, true, false, false, $callback);
while (count($channel->callbacks)) {
    $channel->wait();
}
$channel->close();
$connection->close();


?>
