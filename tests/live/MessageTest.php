<?php

namespace Tests\live;

use Carbon\Carbon;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Header;
use Webklex\PHPIMAP\IMAP;
use Webklex\PHPIMAP\Message;
use Webklex\PHPIMAP\Structure;
use Webklex\PHPIMAP\Support\AttachmentCollection;
use Webklex\PHPIMAP\Support\FlagCollection;
use Webklex\PHPIMAP\Support\Masks\AttachmentMask;
use Webklex\PHPIMAP\Support\Masks\MessageMask;

class MessageTest extends LiveMailboxTestCase
{
    protected function getDefaultMessage(): Message
    {
        $folder = $this->getFolder('INBOX');

        $message = $this->appendMessageTemplate($folder, 'plain.eml');
        self::assertInstanceOf(Message::class, $message);

        return $message;
    }

    public function test_convert_encoding(): void
    {
        $message = $this->getDefaultMessage();
        self::assertEquals('Entwürfe+', $message->convertEncoding('Entw&APw-rfe+', 'UTF7-IMAP'));

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_thread(): void
    {
        $client = $this->getClient();

        $delimiter = $this->getManager()->get('options.delimiter');
        $folder_path = implode($delimiter, ['INBOX', 'thread']);

        $folder = $client->getFolder($folder_path);
        if ($folder !== null) {
            self::assertTrue($this->deleteFolder($folder));
        }
        $folder = $client->createFolder($folder_path, false);

        $message1 = $this->appendMessageTemplate($folder, 'thread_my_topic.eml');
        $message2 = $this->appendMessageTemplate($folder, 'thread_re_my_topic.eml');
        $message3 = $this->appendMessageTemplate($folder, 'thread_unrelated.eml');

        $thread = $message1->thread($folder);
        self::assertCount(2, $thread);

        $thread = $message2->thread($folder);
        self::assertCount(2, $thread);

        $thread = $message3->thread($folder);
        self::assertCount(1, $thread);

        // Cleanup
        self::assertTrue($message1->delete());
        self::assertTrue($message2->delete());
        self::assertTrue($message3->delete());
        $client->expunge();

        self::assertTrue($this->deleteFolder($folder));
    }

    public function test_has_attachments(): void
    {
        $message = $this->getDefaultMessage();
        self::assertFalse($message->hasAttachments());

        $folder = $message->getFolder();
        self::assertInstanceOf(Folder::class, $folder);
        self::assertTrue($message->delete());

        $message = $this->appendMessageTemplate($folder, 'example_attachment.eml');
        self::assertInstanceOf(Message::class, $message);
        self::assertTrue($message->hasAttachments());

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_get_fetch_options(): void
    {
        $message = $this->getDefaultMessage();
        self::assertEquals(IMAP::FT_PEEK, $message->getFetchOptions());

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_get_message_id(): void
    {
        $folder = $this->getFolder('INBOX');
        $message = $this->appendMessageTemplate($folder, 'example_attachment.eml');
        self::assertInstanceOf(Message::class, $message);

        self::assertEquals('d3a5e91963cb805cee975687d5acb1c6@swift.generated', $message->getMessageId());

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_get_reply_to(): void
    {
        $folder = $this->getFolder('INBOX');
        $message = $this->appendMessageTemplate($folder, 'example_attachment.eml');
        self::assertInstanceOf(Message::class, $message);

        self::assertEquals('testreply_to <someone@domain.tld>', $message->getReplyTo());
        self::assertEquals('someone@domain.tld', $message->getReplyTo()->first()->mail);
        self::assertEquals('testreply_to', $message->getReplyTo()->first()->personal);

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_set_sequence(): void
    {
        $message = $this->getDefaultMessage();
        self::assertEquals($message->uid, $message->getSequenceId());

        $message->setSequence(IMAP::ST_MSGN);
        self::assertEquals($message->msgn, $message->getSequenceId());

        $message->setSequence(null);
        self::assertEquals($message->uid, $message->getSequenceId());

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_get_event(): void
    {
        $message = $this->getDefaultMessage();

        $message->setEvent('message', 'test', 'test');
        self::assertEquals('test', $message->getEvent('message', 'test'));

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test__construct(): void
    {
        $message = $this->getDefaultMessage();

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_set_flag(): void
    {
        $message = $this->getDefaultMessage();

        self::assertTrue($message->setFlag('seen'));
        self::assertTrue($message->getFlags()->has('seen'));

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_get_msgn(): void
    {
        $client = $this->getClient();

        $delimiter = $this->getManager()->get('options.delimiter');
        $folder_path = implode($delimiter, ['INBOX', 'test']);

        $folder = $client->getFolder($folder_path);
        if ($folder !== null) {
            self::assertTrue($this->deleteFolder($folder));
        }
        $folder = $client->createFolder($folder_path, false);

        $message = $this->appendMessageTemplate($folder, 'plain.eml');
        self::assertInstanceOf(Message::class, $message);

        self::assertEquals(1, $message->getMsgn());

        // Cleanup
        self::assertTrue($message->delete());
        self::assertTrue($this->deleteFolder($folder));
    }

    public function test_peek(): void
    {
        $message = $this->getDefaultMessage();
        self::assertFalse($message->getFlags()->has('seen'));
        self::assertEquals(IMAP::FT_PEEK, $message->getFetchOptions());
        $message->peek();
        self::assertFalse($message->getFlags()->has('seen'));

        $message->setFetchOption(IMAP::FT_UID);
        self::assertEquals(IMAP::FT_UID, $message->getFetchOptions());
        $message->peek();
        self::assertTrue($message->getFlags()->has('seen'));

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_unset_flag(): void
    {
        $message = $this->getDefaultMessage();

        self::assertFalse($message->getFlags()->has('seen'));

        self::assertTrue($message->setFlag('seen'));
        self::assertTrue($message->getFlags()->has('seen'));

        self::assertTrue($message->unsetFlag('seen'));
        self::assertFalse($message->getFlags()->has('seen'));

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_set_sequence_id(): void
    {
        $message = $this->getDefaultMessage();
        self::assertEquals($message->uid, $message->getSequenceId());

        $original_sequence = $message->getSequenceId();

        $message->setSequenceId(1, IMAP::ST_MSGN);
        self::assertEquals(1, $message->getSequenceId());

        $message->setSequenceId(1);
        self::assertEquals(1, $message->getSequenceId());

        $message->setSequenceId($original_sequence);

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_get_to(): void
    {
        $message = $this->getDefaultMessage();
        $folder = $message->getFolder();
        self::assertInstanceOf(Folder::class, $folder);

        self::assertEquals('to@someone-else.com', $message->getTo());
        self::assertTrue($message->delete());

        $message = $this->appendMessageTemplate($folder, 'example_attachment.eml');
        self::assertInstanceOf(Message::class, $message);

        self::assertEquals('testnameto <someone@domain.tld>', $message->getTo());
        self::assertEquals('testnameto', $message->getTo()->first()->personal);
        self::assertEquals('someone@domain.tld', $message->getTo()->first()->mail);

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_set_uid(): void
    {
        $message = $this->getDefaultMessage();
        self::assertEquals($message->uid, $message->getSequenceId());

        $original_sequence = $message->getSequenceId();

        $message->setUid(789);
        self::assertEquals(789, $message->uid);

        $message->setUid($original_sequence);

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_get_uid(): void
    {
        $message = $this->getDefaultMessage();
        self::assertEquals($message->uid, $message->getSequenceId());

        $original_sequence = $message->uid;

        $message->setUid(789);
        self::assertEquals(789, $message->uid);
        self::assertEquals(789, $message->getUid());

        $message->setUid($original_sequence);

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_has_text_body(): void
    {
        $message = $this->getDefaultMessage();
        self::assertTrue($message->hasTextBody());

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test__get(): void
    {
        $message = $this->getDefaultMessage();
        self::assertEquals($message->uid, $message->getSequenceId());
        self::assertEquals('Example', $message->subject);
        self::assertEquals('to@someone-else.com', $message->to);

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_get_date(): void
    {
        $message = $this->getDefaultMessage();
        self::assertInstanceOf(Carbon::class, $message->getDate()->toDate());

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_set_mask(): void
    {
        $message = $this->getDefaultMessage();
        self::assertEquals(MessageMask::class, $message->getMask());

        $message->setMask(AttachmentMask::class);
        self::assertEquals(AttachmentMask::class, $message->getMask());

        $message->setMask(MessageMask::class);
        self::assertEquals(MessageMask::class, $message->getMask());

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_get_sequence_id(): void
    {
        $message = $this->getDefaultMessage();
        self::assertEquals($message->uid, $message->getSequenceId());

        $original_sequence = $message->getSequenceId();

        $message->setSequenceId(789, IMAP::ST_MSGN);
        self::assertEquals(789, $message->getSequenceId());

        $message->setSequenceId(789);
        self::assertEquals(789, $message->getSequenceId());

        $message->setSequenceId($original_sequence);

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_set_config(): void
    {
        $message = $this->getDefaultMessage();

        $config = $message->getConfig();
        self::assertIsArray($config);

        $message->setConfig(['foo' => 'bar']);
        self::assertArrayHasKey('foo', $message->getConfig());

        $message->setConfig($config);

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_get_events(): void
    {
        $message = $this->getDefaultMessage();

        $events = $message->getEvents();
        self::assertIsArray($events);

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_set_fetch_option(): void
    {
        $message = $this->getDefaultMessage();

        $fetch_option = $message->fetch_options;

        $message->setFetchOption(IMAP::FT_UID);
        self::assertEquals(IMAP::FT_UID, $message->fetch_options);

        $message->setFetchOption(IMAP::FT_PEEK);
        self::assertEquals(IMAP::FT_PEEK, $message->fetch_options);

        $message->setFetchOption(IMAP::FT_UID | IMAP::FT_PEEK);
        self::assertEquals(IMAP::FT_UID | IMAP::FT_PEEK, $message->fetch_options);

        $message->setFetchOption($fetch_option);

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_get_msglist(): void
    {
        $message = $this->getDefaultMessage();

        self::assertEquals(0, (int) $message->getMsglist()->toString());

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_decode_string(): void
    {
        $message = $this->getDefaultMessage();

        $string = '<p class=3D"MsoNormal">Test<o:p></o:p></p>';
        self::assertEquals('<p class="MsoNormal">Test<o:p></o:p></p>', $message->decodeString($string, IMAP::MESSAGE_ENC_QUOTED_PRINTABLE));

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_attachments(): void
    {
        $folder = $this->getFolder('INBOX');

        $message = $this->appendMessageTemplate($folder, 'example_attachment.eml');
        self::assertTrue($message->hasAttachments());
        self::assertSameSize([1], $message->attachments());

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_get_mask(): void
    {
        $message = $this->getDefaultMessage();
        self::assertEquals(MessageMask::class, $message->getMask());

        $message->setMask(AttachmentMask::class);
        self::assertEquals(AttachmentMask::class, $message->getMask());

        $message->setMask(MessageMask::class);
        self::assertEquals(MessageMask::class, $message->getMask());

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_has_html_body(): void
    {
        $folder = $this->getFolder('INBOX');

        $message = $this->appendMessageTemplate($folder, '1366671050@github.com.eml');
        self::assertTrue($message->hasHTMLBody());

        // Cleanup
        self::assertTrue($message->delete());

        $message = $this->getDefaultMessage();
        self::assertFalse($message->hasHTMLBody());

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_set_events(): void
    {
        $message = $this->getDefaultMessage();

        $events = $message->getEvents();
        self::assertIsArray($events);

        $message->setEvents(['foo' => 'bar']);
        self::assertArrayHasKey('foo', $message->getEvents());

        $message->setEvents($events);

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test__set(): void
    {
        $message = $this->getDefaultMessage();

        $message->foo = 'bar';
        self::assertEquals('bar', $message->getFoo());

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_get_html_body(): void
    {
        $folder = $this->getFolder('INBOX');

        $message = $this->appendMessageTemplate($folder, '1366671050@github.com.eml');
        self::assertTrue($message->hasHTMLBody());
        self::assertIsString($message->getHTMLBody());

        // Cleanup
        self::assertTrue($message->delete());

        $message = $this->getDefaultMessage();
        self::assertFalse($message->hasHTMLBody());
        self::assertEmpty($message->getHTMLBody());

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_get_sequence(): void
    {
        $message = $this->getDefaultMessage();
        self::assertEquals(IMAP::ST_UID, $message->getSequence());

        $original_sequence = $message->getSequence();

        $message->setSequence(IMAP::ST_MSGN);
        self::assertEquals(IMAP::ST_MSGN, $message->getSequence());

        $message->setSequence($original_sequence);
        self::assertEquals(IMAP::ST_UID, $message->getSequence());

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_restore(): void
    {
        $message = $this->getDefaultMessage();

        $message->setFlag('deleted');
        self::assertTrue($message->hasFlag('deleted'));

        $message->restore();
        self::assertFalse($message->hasFlag('deleted'));

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_get_priority(): void
    {
        $folder = $this->getFolder('INBOX');

        $message = $this->appendMessageTemplate($folder, 'example_attachment.eml');
        self::assertEquals(1, $message->getPriority()->first());

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_set_attachments(): void
    {
        $message = $this->getDefaultMessage();

        $message->setAttachments(new AttachmentCollection(['foo' => 'bar']));
        self::assertIsArray($message->attachments()->toArray());
        self::assertTrue($message->attachments()->has('foo'));

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_get_from(): void
    {
        $message = $this->getDefaultMessage();
        self::assertEquals('from@someone.com', $message->getFrom()->first()->mail);

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_set_event(): void
    {
        $message = $this->getDefaultMessage();

        $message->setEvent('message', 'bar', 'foo');
        self::assertArrayHasKey('bar', $message->getEvents()['message']);

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_get_in_reply_to(): void
    {
        $message = $this->getDefaultMessage();
        self::assertEquals('', $message->getInReplyTo());

        // Cleanup
        self::assertTrue($message->delete());

        $folder = $this->getFolder('INBOX');

        $message = $this->appendMessageTemplate($folder, '1366671050@github.com.eml');
        self::assertEquals('Webklex/php-imap/issues/349@github.com', $message->getInReplyTo());

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_copy(): void
    {
        $message = $this->getDefaultMessage();
        $client = $message->getClient();
        self::assertInstanceOf(Client::class, $client);

        $delimiter = $this->getManager()->get('options.delimiter');
        $folder_path = implode($delimiter, ['INBOX', 'test']);

        $folder = $client->getFolder($folder_path);
        if ($folder !== null) {
            self::assertTrue($this->deleteFolder($folder));
        }
        $folder = $client->createFolder($folder_path, false);
        self::assertInstanceOf(Folder::class, $folder);

        $new_message = $message->copy($folder->path, true);
        self::assertInstanceOf(Message::class, $new_message);
        self::assertEquals($folder->path, $new_message->getFolder()->path);

        // Cleanup
        self::assertTrue($message->delete());
        self::assertTrue($new_message->delete());
    }

    public function test_get_bodies(): void
    {
        $message = $this->getDefaultMessage();
        self::assertIsArray($message->getBodies());
        self::assertCount(1, $message->getBodies());

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_get_flags(): void
    {
        $message = $this->getDefaultMessage();
        self::assertIsArray($message->getFlags()->all());

        self::assertFalse($message->hasFlag('seen'));

        self::assertTrue($message->setFlag('seen'));
        self::assertTrue($message->getFlags()->has('seen'));
        self::assertTrue($message->hasFlag('seen'));

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_add_flag(): void
    {
        $message = $this->getDefaultMessage();
        self::assertFalse($message->hasFlag('seen'));

        self::assertTrue($message->addFlag('seen'));
        self::assertTrue($message->hasFlag('seen'));

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_get_subject(): void
    {
        $message = $this->getDefaultMessage();
        self::assertEquals('Example', $message->getSubject());

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_get_client(): void
    {
        $message = $this->getDefaultMessage();
        self::assertInstanceOf(Client::class, $message->getClient());

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_set_fetch_flags_option(): void
    {
        $message = $this->getDefaultMessage();

        self::assertTrue($message->getFetchFlagsOption());
        $message->setFetchFlagsOption(false);
        self::assertFalse($message->getFetchFlagsOption());

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_mask(): void
    {
        $message = $this->getDefaultMessage();
        self::assertInstanceOf(MessageMask::class, $message->mask());

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_set_msglist(): void
    {
        $message = $this->getDefaultMessage();
        $message->setMsglist('foo');
        self::assertEquals('foo', $message->getMsglist());

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_flags(): void
    {
        $message = $this->getDefaultMessage();
        self::assertInstanceOf(FlagCollection::class, $message->flags());

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_get_attributes(): void
    {
        $message = $this->getDefaultMessage();
        self::assertIsArray($message->getAttributes());
        self::assertArrayHasKey('subject', $message->getAttributes());

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_get_attachments(): void
    {
        $message = $this->getDefaultMessage();
        self::assertInstanceOf(AttachmentCollection::class, $message->getAttachments());

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_get_raw_body(): void
    {
        $message = $this->getDefaultMessage();
        self::assertIsString($message->getRawBody());

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_is(): void
    {
        $message = $this->getDefaultMessage();
        self::assertTrue($message->is($message));

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_set_flags(): void
    {
        $message = $this->getDefaultMessage();
        $message->setFlags(new FlagCollection);
        self::assertFalse($message->hasFlag('recent'));

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_make(): void
    {
        $folder = $this->getFolder('INBOX');
        $folder->getClient()->openFolder($folder->path);

        $email = file_get_contents(implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'messages', '1366671050@github.com.eml']));
        if (! str_contains($email, "\r\n")) {
            $email = str_replace("\n", "\r\n", $email);
        }

        $raw_header = substr($email, 0, strpos($email, "\r\n\r\n"));
        $raw_body = substr($email, strlen($raw_header) + 8);

        $message = Message::make(0, null, $folder->getClient(), $raw_header, $raw_body, [0 => '\\Seen'], IMAP::ST_UID);
        self::assertInstanceOf(Message::class, $message);
    }

    public function test_set_available_flags(): void
    {
        $message = $this->getDefaultMessage();

        $message->setAvailableFlags(['foo']);
        self::assertSameSize(['foo'], $message->getAvailableFlags());
        self::assertEquals('foo', $message->getAvailableFlags()[0]);

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_get_sender(): void
    {
        $folder = $this->getFolder('INBOX');

        $message = $this->appendMessageTemplate($folder, 'example_attachment.eml');
        self::assertEquals('testsender <someone@domain.tld>', $message->getSender());
        self::assertEquals('testsender', $message->getSender()->first()->personal);
        self::assertEquals('someone@domain.tld', $message->getSender()->first()->mail);

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_from_file(): void
    {
        $this->getManager();
        $filename = implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'messages', '1366671050@github.com.eml']);
        $message = Message::fromFile($filename);
        self::assertInstanceOf(Message::class, $message);
    }

    public function test_get_structure(): void
    {
        $message = $this->getDefaultMessage();
        self::assertInstanceOf(Structure::class, $message->getStructure());

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_get(): void
    {
        $message = $this->getDefaultMessage();
        self::assertEquals('Example', $message->get('subject'));

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_get_size(): void
    {
        $message = $this->getDefaultMessage();
        self::assertEquals(214, $message->getSize());

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_get_header(): void
    {
        $message = $this->getDefaultMessage();
        self::assertInstanceOf(Header::class, $message->getHeader());

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_get_references(): void
    {
        $folder = $this->getFolder('INBOX');

        $message = $this->appendMessageTemplate($folder, '1366671050@github.com.eml');
        self::assertIsArray($message->getReferences()->all());
        self::assertEquals('Webklex/php-imap/issues/349@github.com', $message->getReferences()->first());

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_set_folder_path(): void
    {
        $message = $this->getDefaultMessage();

        $folder_path = $message->getFolderPath();

        $message->setFolderPath('foo');
        self::assertEquals('foo', $message->getFolderPath());

        $message->setFolderPath($folder_path);

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_get_text_body(): void
    {
        $message = $this->getDefaultMessage();
        self::assertIsString($message->getTextBody());

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_move(): void
    {
        $message = $this->getDefaultMessage();
        $client = $message->getClient();
        self::assertInstanceOf(Client::class, $client);

        $delimiter = $this->getManager()->get('options.delimiter');
        $folder_path = implode($delimiter, ['INBOX', 'test']);

        $folder = $client->getFolder($folder_path);
        if ($folder !== null) {
            self::assertTrue($this->deleteFolder($folder));
        }
        $folder = $client->createFolder($folder_path, false);
        self::assertInstanceOf(Folder::class, $folder);

        $message = $message->move($folder->path, true);
        self::assertInstanceOf(Message::class, $message);
        self::assertEquals($folder->path, $message->getFolder()->path);

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_get_folder_path(): void
    {
        $message = $this->getDefaultMessage();
        self::assertEquals('INBOX', $message->getFolderPath());

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_get_folder(): void
    {
        $message = $this->getDefaultMessage();
        self::assertInstanceOf(Folder::class, $message->getFolder());

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_get_fetch_body_option(): void
    {
        $message = $this->getDefaultMessage();
        self::assertTrue($message->getFetchBodyOption());

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_set_fetch_body_option(): void
    {
        $message = $this->getDefaultMessage();

        $message->setFetchBodyOption(false);
        self::assertFalse($message->getFetchBodyOption());

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_get_fetch_flags_option(): void
    {
        $message = $this->getDefaultMessage();
        self::assertTrue($message->getFetchFlagsOption());

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test__call(): void
    {
        $message = $this->getDefaultMessage();
        self::assertEquals('Example', $message->getSubject());

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_set_client(): void
    {
        $message = $this->getDefaultMessage();
        $client = $message->getClient();
        self::assertInstanceOf(Client::class, $client);

        $message->setClient(null);
        self::assertNull($message->getClient());

        $message->setClient($client);
        self::assertInstanceOf(Client::class, $message->getClient());

        // Cleanup
        self::assertTrue($message->delete());
    }

    public function test_set_msgn(): void
    {
        $message = $this->getDefaultMessage();

        $uid = $message->getUid();
        $message->setMsgn(789);
        self::assertEquals(789, $message->getMsgn());
        $message->setUid($uid);

        // Cleanup
        self::assertTrue($message->delete());
    }
}
