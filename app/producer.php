<?php
require_once __DIR__ . '/../vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

$connection = new AMQPConnection('localhost', 5672, 'guest', 'guest');
$channel = $connection->channel();
$channel->exchange_declare('articles', 'fanout', false, false, false);
$channel->exchange_declare('articles', 'fanout', true, false, false);
$data = implode(' ', array_slice($argv, 1));
$date = new DateTime();
$messageId = uniqid();
$appId = implode(' ', array_slice($argv, 1));
if (empty($appId))
{
    $appId = 'some producer';
}
$messageBody = $date->format('H:i:s');
$properties = array(
    'delivery_mode' => 2,
    'message_id' => $messageId,
    'app_id' => $appId
);
$msg = new AMQPMessage($messageBody, $properties);
$channel->basic_publish($msg, 'articles');
echo " [x] Sent message id={$messageId} data='", $messageBody, "'\n";
$channel->close();
$connection->close();
?>
