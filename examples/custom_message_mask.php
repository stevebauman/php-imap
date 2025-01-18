<?php

class CustomMessageMask extends \Webklex\PHPIMAP\Support\Masks\MessageMask
{
    public function token(): string
    {
        return implode('-', [$this->message_id, $this->uid, $this->message_no]);
    }

    public function getAttachmentCount(): int
    {
        return $this->getAttachments()->count();
    }
}

/** @var \Webklex\PHPIMAP\Client $client */
$cm = new \Webklex\PHPIMAP\ClientManager('path/to/config/imap.php');
$client = $cm->account('default');
$client->connect();

/** @var \Webklex\PHPIMAP\Folder $folder */
$folder = $client->getFolder('INBOX');

/** @var \Webklex\PHPIMAP\Message $message */
$message = $folder->query()->limit(1)->get()->first();

/** @var CustomMessageMask $masked_message */
$masked_message = $message->mask(CustomMessageMask::class);

echo 'Token for uid ['.$masked_message->uid.']: '.$masked_message->token().' @atms:'.$masked_message->getAttachmentCount();

$masked_message->setFlag('seen');
