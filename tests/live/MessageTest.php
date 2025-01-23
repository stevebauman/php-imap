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
        $this->assertInstanceOf(Message::class, $message);

        return $message;
    }

    public function test_convert_encoding(): void
    {
        $message = $this->getDefaultMessage();
        $this->assertEquals('Entwürfe+', $message->convertEncoding('Entw&APw-rfe+', 'UTF7-IMAP'));

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_thread(): void
    {
        $client = $this->getClient();

        $delimiter = $this->getManager()->get('options.delimiter');
        $folder_path = implode($delimiter, ['INBOX', 'thread']);

        $folder = $client->getFolder($folder_path);
        if ($folder !== null) {
            $this->assertTrue($this->deleteFolder($folder));
        }
        $folder = $client->createFolder($folder_path, false);

        $message1 = $this->appendMessageTemplate($folder, 'thread_my_topic.eml');
        $message2 = $this->appendMessageTemplate($folder, 'thread_re_my_topic.eml');
        $message3 = $this->appendMessageTemplate($folder, 'thread_unrelated.eml');

        $thread = $message1->thread($folder);
        $this->assertCount(2, $thread);

        $thread = $message2->thread($folder);
        $this->assertCount(2, $thread);

        $thread = $message3->thread($folder);
        $this->assertCount(1, $thread);

        // Cleanup
        $this->assertTrue($message1->delete());
        $this->assertTrue($message2->delete());
        $this->assertTrue($message3->delete());
        $client->expunge();

        $this->assertTrue($this->deleteFolder($folder));
    }

    public function test_has_attachments(): void
    {
        $message = $this->getDefaultMessage();
        $this->assertFalse($message->hasAttachments());

        $folder = $message->getFolder();
        $this->assertInstanceOf(Folder::class, $folder);
        $this->assertTrue($message->delete());

        $message = $this->appendMessageTemplate($folder, 'example_attachment.eml');
        $this->assertInstanceOf(Message::class, $message);
        $this->assertTrue($message->hasAttachments());

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_get_fetch_options(): void
    {
        $message = $this->getDefaultMessage();
        $this->assertEquals(IMAP::FT_PEEK, $message->getFetchOptions());

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_get_message_id(): void
    {
        $folder = $this->getFolder('INBOX');
        $message = $this->appendMessageTemplate($folder, 'example_attachment.eml');
        $this->assertInstanceOf(Message::class, $message);

        $this->assertEquals('d3a5e91963cb805cee975687d5acb1c6@swift.generated', $message->getMessageId());

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_get_reply_to(): void
    {
        $folder = $this->getFolder('INBOX');
        $message = $this->appendMessageTemplate($folder, 'example_attachment.eml');
        $this->assertInstanceOf(Message::class, $message);

        $this->assertEquals('testreply_to <someone@domain.tld>', $message->getReplyTo());
        $this->assertEquals('someone@domain.tld', $message->getReplyTo()->first()->mail);
        $this->assertEquals('testreply_to', $message->getReplyTo()->first()->personal);

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_set_sequence(): void
    {
        $message = $this->getDefaultMessage();
        $this->assertEquals($message->uid, $message->getSequenceId());

        $message->setSequence(IMAP::ST_MSGN);
        $this->assertEquals($message->msgn, $message->getSequenceId());

        $message->setSequence(null);
        $this->assertEquals($message->uid, $message->getSequenceId());

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_get_event(): void
    {
        $message = $this->getDefaultMessage();

        $message->setEvent('message', 'test', 'test');
        $this->assertEquals('test', $message->getEvent('message', 'test'));

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test__construct(): void
    {
        $message = $this->getDefaultMessage();

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_set_flag(): void
    {
        $message = $this->getDefaultMessage();

        $this->assertTrue($message->setFlag('seen'));
        $this->assertTrue($message->getFlags()->has('seen'));

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_get_msgn(): void
    {
        $client = $this->getClient();

        $delimiter = $this->getManager()->get('options.delimiter');
        $folder_path = implode($delimiter, ['INBOX', 'test']);

        $folder = $client->getFolder($folder_path);
        if ($folder !== null) {
            $this->assertTrue($this->deleteFolder($folder));
        }
        $folder = $client->createFolder($folder_path, false);

        $message = $this->appendMessageTemplate($folder, 'plain.eml');
        $this->assertInstanceOf(Message::class, $message);

        $this->assertEquals(1, $message->getMsgn());

        // Cleanup
        $this->assertTrue($message->delete());
        $this->assertTrue($this->deleteFolder($folder));
    }

    public function test_peek(): void
    {
        $message = $this->getDefaultMessage();
        $this->assertFalse($message->getFlags()->has('seen'));
        $this->assertEquals(IMAP::FT_PEEK, $message->getFetchOptions());
        $message->peek();
        $this->assertFalse($message->getFlags()->has('seen'));

        $message->setFetchOption(IMAP::FT_UID);
        $this->assertEquals(IMAP::FT_UID, $message->getFetchOptions());
        $message->peek();
        $this->assertTrue($message->getFlags()->has('seen'));

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_unset_flag(): void
    {
        $message = $this->getDefaultMessage();

        $this->assertFalse($message->getFlags()->has('seen'));

        $this->assertTrue($message->setFlag('seen'));
        $this->assertTrue($message->getFlags()->has('seen'));

        $this->assertTrue($message->unsetFlag('seen'));
        $this->assertFalse($message->getFlags()->has('seen'));

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_set_sequence_id(): void
    {
        $message = $this->getDefaultMessage();
        $this->assertEquals($message->uid, $message->getSequenceId());

        $original_sequence = $message->getSequenceId();

        $message->setSequenceId(1, IMAP::ST_MSGN);
        $this->assertEquals(1, $message->getSequenceId());

        $message->setSequenceId(1);
        $this->assertEquals(1, $message->getSequenceId());

        $message->setSequenceId($original_sequence);

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_get_to(): void
    {
        $message = $this->getDefaultMessage();
        $folder = $message->getFolder();
        $this->assertInstanceOf(Folder::class, $folder);

        $this->assertEquals('to@someone-else.com', $message->getTo());
        $this->assertTrue($message->delete());

        $message = $this->appendMessageTemplate($folder, 'example_attachment.eml');
        $this->assertInstanceOf(Message::class, $message);

        $this->assertEquals('testnameto <someone@domain.tld>', $message->getTo());
        $this->assertEquals('testnameto', $message->getTo()->first()->personal);
        $this->assertEquals('someone@domain.tld', $message->getTo()->first()->mail);

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_set_uid(): void
    {
        $message = $this->getDefaultMessage();
        $this->assertEquals($message->uid, $message->getSequenceId());

        $original_sequence = $message->getSequenceId();

        $message->setUid(789);
        $this->assertEquals(789, $message->uid);

        $message->setUid($original_sequence);

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_get_uid(): void
    {
        $message = $this->getDefaultMessage();
        $this->assertEquals($message->uid, $message->getSequenceId());

        $original_sequence = $message->uid;

        $message->setUid(789);
        $this->assertEquals(789, $message->uid);
        $this->assertEquals(789, $message->getUid());

        $message->setUid($original_sequence);

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_has_text_body(): void
    {
        $message = $this->getDefaultMessage();
        $this->assertTrue($message->hasTextBody());

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test__get(): void
    {
        $message = $this->getDefaultMessage();
        $this->assertEquals($message->uid, $message->getSequenceId());
        $this->assertEquals('Example', $message->subject);
        $this->assertEquals('to@someone-else.com', $message->to);

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_get_date(): void
    {
        $message = $this->getDefaultMessage();
        $this->assertInstanceOf(Carbon::class, $message->getDate()->toDate());

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_set_mask(): void
    {
        $message = $this->getDefaultMessage();
        $this->assertEquals(MessageMask::class, $message->getMask());

        $message->setMask(AttachmentMask::class);
        $this->assertEquals(AttachmentMask::class, $message->getMask());

        $message->setMask(MessageMask::class);
        $this->assertEquals(MessageMask::class, $message->getMask());

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_get_sequence_id(): void
    {
        $message = $this->getDefaultMessage();
        $this->assertEquals($message->uid, $message->getSequenceId());

        $original_sequence = $message->getSequenceId();

        $message->setSequenceId(789, IMAP::ST_MSGN);
        $this->assertEquals(789, $message->getSequenceId());

        $message->setSequenceId(789);
        $this->assertEquals(789, $message->getSequenceId());

        $message->setSequenceId($original_sequence);

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_set_config(): void
    {
        $message = $this->getDefaultMessage();

        $config = $message->getConfig();
        $this->assertIsArray($config);

        $message->setConfig(['foo' => 'bar']);
        $this->assertArrayHasKey('foo', $message->getConfig());

        $message->setConfig($config);

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_get_events(): void
    {
        $message = $this->getDefaultMessage();

        $events = $message->getEvents();
        $this->assertIsArray($events);

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_set_fetch_option(): void
    {
        $message = $this->getDefaultMessage();

        $fetch_option = $message->fetchOptions;

        $message->setFetchOption(IMAP::FT_UID);
        $this->assertEquals(IMAP::FT_UID, $message->fetchOptions);

        $message->setFetchOption(IMAP::FT_PEEK);
        $this->assertEquals(IMAP::FT_PEEK, $message->fetchOptions);

        $message->setFetchOption(IMAP::FT_UID | IMAP::FT_PEEK);
        $this->assertEquals(IMAP::FT_UID | IMAP::FT_PEEK, $message->fetchOptions);

        $message->setFetchOption($fetch_option);

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_get_msglist(): void
    {
        $message = $this->getDefaultMessage();

        $this->assertEquals(0, (int) $message->getMsglist()->toString());

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_decode_string(): void
    {
        $message = $this->getDefaultMessage();

        $string = '<p class=3D"MsoNormal">Test<o:p></o:p></p>';
        $this->assertEquals('<p class="MsoNormal">Test<o:p></o:p></p>', $message->decodeString($string, IMAP::MESSAGE_ENC_QUOTED_PRINTABLE));

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_attachments(): void
    {
        $folder = $this->getFolder('INBOX');

        $message = $this->appendMessageTemplate($folder, 'example_attachment.eml');
        $this->assertTrue($message->hasAttachments());
        $this->assertSameSize([1], $message->attachments());

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_get_mask(): void
    {
        $message = $this->getDefaultMessage();
        $this->assertEquals(MessageMask::class, $message->getMask());

        $message->setMask(AttachmentMask::class);
        $this->assertEquals(AttachmentMask::class, $message->getMask());

        $message->setMask(MessageMask::class);
        $this->assertEquals(MessageMask::class, $message->getMask());

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_has_html_body(): void
    {
        $folder = $this->getFolder('INBOX');

        $message = $this->appendMessageTemplate($folder, '1366671050@github.com.eml');
        $this->assertTrue($message->hasHTMLBody());

        // Cleanup
        $this->assertTrue($message->delete());

        $message = $this->getDefaultMessage();
        $this->assertFalse($message->hasHTMLBody());

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_set_events(): void
    {
        $message = $this->getDefaultMessage();

        $events = $message->getEvents();
        $this->assertIsArray($events);

        $message->setEvents(['foo' => 'bar']);
        $this->assertArrayHasKey('foo', $message->getEvents());

        $message->setEvents($events);

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test__set(): void
    {
        $message = $this->getDefaultMessage();

        $message->foo = 'bar';
        $this->assertEquals('bar', $message->getFoo());

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_get_html_body(): void
    {
        $folder = $this->getFolder('INBOX');

        $message = $this->appendMessageTemplate($folder, '1366671050@github.com.eml');
        $this->assertTrue($message->hasHTMLBody());
        $this->assertIsString($message->getHTMLBody());

        // Cleanup
        $this->assertTrue($message->delete());

        $message = $this->getDefaultMessage();
        $this->assertFalse($message->hasHTMLBody());
        $this->assertEmpty($message->getHTMLBody());

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_get_sequence(): void
    {
        $message = $this->getDefaultMessage();
        $this->assertEquals(IMAP::ST_UID, $message->getSequence());

        $original_sequence = $message->getSequence();

        $message->setSequence(IMAP::ST_MSGN);
        $this->assertEquals(IMAP::ST_MSGN, $message->getSequence());

        $message->setSequence($original_sequence);
        $this->assertEquals(IMAP::ST_UID, $message->getSequence());

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_restore(): void
    {
        $message = $this->getDefaultMessage();

        $message->setFlag('deleted');
        $this->assertTrue($message->hasFlag('deleted'));

        $message->restore();
        $this->assertFalse($message->hasFlag('deleted'));

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_get_priority(): void
    {
        $folder = $this->getFolder('INBOX');

        $message = $this->appendMessageTemplate($folder, 'example_attachment.eml');
        $this->assertEquals(1, $message->getPriority()->first());

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_set_attachments(): void
    {
        $message = $this->getDefaultMessage();

        $message->setAttachments(new AttachmentCollection(['foo' => 'bar']));
        $this->assertIsArray($message->attachments()->toArray());
        $this->assertTrue($message->attachments()->has('foo'));

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_get_from(): void
    {
        $message = $this->getDefaultMessage();
        $this->assertEquals('from@someone.com', $message->getFrom()->first()->mail);

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_set_event(): void
    {
        $message = $this->getDefaultMessage();

        $message->setEvent('message', 'bar', 'foo');
        $this->assertArrayHasKey('bar', $message->getEvents()['message']);

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_get_in_reply_to(): void
    {
        $message = $this->getDefaultMessage();
        $this->assertEquals('', $message->getInReplyTo());

        // Cleanup
        $this->assertTrue($message->delete());

        $folder = $this->getFolder('INBOX');

        $message = $this->appendMessageTemplate($folder, '1366671050@github.com.eml');
        $this->assertEquals('Webklex/php-imap/issues/349@github.com', $message->getInReplyTo());

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_copy(): void
    {
        $message = $this->getDefaultMessage();
        $client = $message->getClient();
        $this->assertInstanceOf(Client::class, $client);

        $delimiter = $this->getManager()->get('options.delimiter');
        $folder_path = implode($delimiter, ['INBOX', 'test']);

        $folder = $client->getFolder($folder_path);
        if ($folder !== null) {
            $this->assertTrue($this->deleteFolder($folder));
        }
        $folder = $client->createFolder($folder_path, false);
        $this->assertInstanceOf(Folder::class, $folder);

        $new_message = $message->copy($folder->path, true);
        $this->assertInstanceOf(Message::class, $new_message);
        $this->assertEquals($folder->path, $new_message->getFolder()->path);

        // Cleanup
        $this->assertTrue($message->delete());
        $this->assertTrue($new_message->delete());
    }

    public function test_get_bodies(): void
    {
        $message = $this->getDefaultMessage();
        $this->assertIsArray($message->getBodies());
        $this->assertCount(1, $message->getBodies());

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_get_flags(): void
    {
        $message = $this->getDefaultMessage();
        $this->assertIsArray($message->getFlags()->all());

        $this->assertFalse($message->hasFlag('seen'));

        $this->assertTrue($message->setFlag('seen'));
        $this->assertTrue($message->getFlags()->has('seen'));
        $this->assertTrue($message->hasFlag('seen'));

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_add_flag(): void
    {
        $message = $this->getDefaultMessage();
        $this->assertFalse($message->hasFlag('seen'));

        $this->assertTrue($message->addFlag('seen'));
        $this->assertTrue($message->hasFlag('seen'));

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_get_subject(): void
    {
        $message = $this->getDefaultMessage();
        $this->assertEquals('Example', $message->getSubject());

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_get_client(): void
    {
        $message = $this->getDefaultMessage();
        $this->assertInstanceOf(Client::class, $message->getClient());

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_set_fetch_flags_option(): void
    {
        $message = $this->getDefaultMessage();

        $this->assertTrue($message->getFetchFlagsOption());
        $message->setFetchFlagsOption(false);
        $this->assertFalse($message->getFetchFlagsOption());

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_mask(): void
    {
        $message = $this->getDefaultMessage();
        $this->assertInstanceOf(MessageMask::class, $message->mask());

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_set_msglist(): void
    {
        $message = $this->getDefaultMessage();
        $message->setMsglist('foo');
        $this->assertEquals('foo', $message->getMsglist());

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_flags(): void
    {
        $message = $this->getDefaultMessage();
        $this->assertInstanceOf(FlagCollection::class, $message->flags());

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_get_attributes(): void
    {
        $message = $this->getDefaultMessage();
        $this->assertIsArray($message->getAttributes());
        $this->assertArrayHasKey('subject', $message->getAttributes());

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_get_attachments(): void
    {
        $message = $this->getDefaultMessage();
        $this->assertInstanceOf(AttachmentCollection::class, $message->getAttachments());

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_get_raw_body(): void
    {
        $message = $this->getDefaultMessage();
        $this->assertIsString($message->getRawBody());

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_is(): void
    {
        $message = $this->getDefaultMessage();
        $this->assertTrue($message->is($message));

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_set_flags(): void
    {
        $message = $this->getDefaultMessage();
        $message->setFlags(new FlagCollection);
        $this->assertFalse($message->hasFlag('recent'));

        // Cleanup
        $this->assertTrue($message->delete());
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
        $this->assertInstanceOf(Message::class, $message);
    }

    public function test_set_available_flags(): void
    {
        $message = $this->getDefaultMessage();

        $message->setAvailableFlags(['foo']);
        $this->assertSameSize(['foo'], $message->getAvailableFlags());
        $this->assertEquals('foo', $message->getAvailableFlags()[0]);

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_get_sender(): void
    {
        $folder = $this->getFolder('INBOX');

        $message = $this->appendMessageTemplate($folder, 'example_attachment.eml');
        $this->assertEquals('testsender <someone@domain.tld>', $message->getSender());
        $this->assertEquals('testsender', $message->getSender()->first()->personal);
        $this->assertEquals('someone@domain.tld', $message->getSender()->first()->mail);

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_from_file(): void
    {
        $this->getManager();
        $filename = implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'messages', '1366671050@github.com.eml']);
        $message = Message::fromFile($filename);
        $this->assertInstanceOf(Message::class, $message);
    }

    public function test_get_structure(): void
    {
        $message = $this->getDefaultMessage();
        $this->assertInstanceOf(Structure::class, $message->getStructure());

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_get(): void
    {
        $message = $this->getDefaultMessage();
        $this->assertEquals('Example', $message->get('subject'));

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_get_size(): void
    {
        $message = $this->getDefaultMessage();
        $this->assertEquals(214, $message->getSize());

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_get_header(): void
    {
        $message = $this->getDefaultMessage();
        $this->assertInstanceOf(Header::class, $message->getHeader());

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_get_references(): void
    {
        $folder = $this->getFolder('INBOX');

        $message = $this->appendMessageTemplate($folder, '1366671050@github.com.eml');
        $this->assertIsArray($message->getReferences()->all());
        $this->assertEquals('Webklex/php-imap/issues/349@github.com', $message->getReferences()->first());

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_set_folder_path(): void
    {
        $message = $this->getDefaultMessage();

        $folder_path = $message->getFolderPath();

        $message->setFolderPath('foo');
        $this->assertEquals('foo', $message->getFolderPath());

        $message->setFolderPath($folder_path);

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_get_text_body(): void
    {
        $message = $this->getDefaultMessage();
        $this->assertIsString($message->getTextBody());

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_move(): void
    {
        $message = $this->getDefaultMessage();
        $client = $message->getClient();
        $this->assertInstanceOf(Client::class, $client);

        $delimiter = $this->getManager()->get('options.delimiter');
        $folder_path = implode($delimiter, ['INBOX', 'test']);

        $folder = $client->getFolder($folder_path);
        if ($folder !== null) {
            $this->assertTrue($this->deleteFolder($folder));
        }
        $folder = $client->createFolder($folder_path, false);
        $this->assertInstanceOf(Folder::class, $folder);

        $message = $message->move($folder->path, true);
        $this->assertInstanceOf(Message::class, $message);
        $this->assertEquals($folder->path, $message->getFolder()->path);

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_get_folder_path(): void
    {
        $message = $this->getDefaultMessage();
        $this->assertEquals('INBOX', $message->getFolderPath());

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_get_folder(): void
    {
        $message = $this->getDefaultMessage();
        $this->assertInstanceOf(Folder::class, $message->getFolder());

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_get_fetch_body_option(): void
    {
        $message = $this->getDefaultMessage();
        $this->assertTrue($message->getFetchBodyOption());

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_set_fetch_body_option(): void
    {
        $message = $this->getDefaultMessage();

        $message->setFetchBodyOption(false);
        $this->assertFalse($message->getFetchBodyOption());

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_get_fetch_flags_option(): void
    {
        $message = $this->getDefaultMessage();
        $this->assertTrue($message->getFetchFlagsOption());

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test__call(): void
    {
        $message = $this->getDefaultMessage();
        $this->assertEquals('Example', $message->getSubject());

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_set_client(): void
    {
        $message = $this->getDefaultMessage();
        $client = $message->getClient();
        $this->assertInstanceOf(Client::class, $client);

        $message->setClient(null);
        $this->assertNull($message->getClient());

        $message->setClient($client);
        $this->assertInstanceOf(Client::class, $message->getClient());

        // Cleanup
        $this->assertTrue($message->delete());
    }

    public function test_set_msgn(): void
    {
        $message = $this->getDefaultMessage();

        $uid = $message->getUid();
        $message->setMsgn(789);
        $this->assertEquals(789, $message->getMsgn());
        $message->setUid($uid);

        // Cleanup
        $this->assertTrue($message->delete());
    }
}
