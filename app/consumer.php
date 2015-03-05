<?php
require_once __DIR__ . '/../vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;
$user = 'guest';
$connection = new AMQPConnection('localhost', 5672, $user, 'guest');
$channel = $connection->channel();
$channel->exchange_declare('articles', 'fanout', false, false, false);
$channel->exchange_declare('articles', 'fanout', true, false, false);



list($queue_name, ,) = $channel->queue_declare("", false, true, true, false);
$channel->queue_bind($queue_name, 'articles');
$appId = implode(' ', array_slice($argv, 1));
if (empty($appId)) {
    $appId = 'unknown consumer';
}
echo ' [**] Waiting for articles', "\n";
$callback = function($msg){
    global $appId;
    $properties = $msg->get_properties();
    $messageId = isset($properties['message_id']) ? $properties['message_id'] : null;
    $producerAppId = isset($properties['app_id']) ? $properties['app_id'] : null;
    $body = $msg->body;
    //
    // do fancy processing stuff her
    echo "[->] Received message id='{$messageId}' from {$producerAppId}  \n";
    $loops = 3;

    for ($i=1; $i<=$loops; $i++) {
        echo " [++] Processing ($i)... \n";
        respond($body, $messageId, $appId, 'processing' , $i/$loops*100 );
        sleep(1);
    }
    echo " [++] Finished! ($i)... \n";
    respond($body, $messageId, $appId, 'done', 100 );
    //echo " [->] {$body} \n";
};
$channel->basic_consume($queue_name, '', false, true, false, false, $callback);
while(count($channel->callbacks)) {
    $channel->wait();
}
$channel->close();
$connection->close();

function respond($result, $messageId, $appId, $status, $percentage) {
    $connection = new AMQPConnection('localhost', 5672, 'guest', 'guest');
    $channel = $connection->channel();
    $channel->exchange_declare('articlestatus', 'fanout', false, false, false);
    list($queue_name, ,) = $channel->queue_declare("", false, true, true, false);
    $channel->queue_bind($queue_name, 'articles');
   // $data = implode(' ', array_slice($argv, 1));
    if ($status == 'done') {
        $data = "Done! Told me it's {$result} but now it's already " . date('H:i:s');
    } else {
        $data = "Processing data... " . round($percentage). "% finished \n" ;
    }

    $properties = array(
        'delivery_mode' => 2,
        'message_id' => $messageId,
        'app_id' => $appId,
        'application_headers' => array('status' => array('S',$status))
    );
    $msg = new AMQPMessage($data, $properties);
    $channel->basic_publish($msg, 'articlestatus');
    echo "[<-] Status '{$status}' for message id={$messageId} has been broadcasted\n";
    $channel->close();
    $connection->close();
}

?>
