<?php
/*
* File: MessageTest.php
* Category: -
* Author: M.Goldenbaum
* Created: 07.03.23 20:21
* Updated: -
*
* Description:
*  -
*/

namespace Tests\live;

use Carbon\Carbon;
use ReflectionException;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\Exceptions\AuthFailedException;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;
use Webklex\PHPIMAP\Exceptions\EventNotFoundException;
use Webklex\PHPIMAP\Exceptions\FolderFetchingException;
use Webklex\PHPIMAP\Exceptions\GetMessagesFailedException;
use Webklex\PHPIMAP\Exceptions\ImapBadRequestException;
use Webklex\PHPIMAP\Exceptions\ImapServerErrorException;
use Webklex\PHPIMAP\Exceptions\InvalidMessageDateException;
use Webklex\PHPIMAP\Exceptions\MaskNotFoundException;
use Webklex\PHPIMAP\Exceptions\MessageContentFetchingException;
use Webklex\PHPIMAP\Exceptions\MessageFlagException;
use Webklex\PHPIMAP\Exceptions\MessageHeaderFetchingException;
use Webklex\PHPIMAP\Exceptions\MessageNotFoundException;
use Webklex\PHPIMAP\Exceptions\MessageSizeFetchingException;
use Webklex\PHPIMAP\Exceptions\ResponseException;
use Webklex\PHPIMAP\Exceptions\RuntimeException;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Header;
use Webklex\PHPIMAP\IMAP;
use Webklex\PHPIMAP\Message;
use Webklex\PHPIMAP\Structure;
use Webklex\PHPIMAP\Support\AttachmentCollection;
use Webklex\PHPIMAP\Support\FlagCollection;
use Webklex\PHPIMAP\Support\Masks\AttachmentMask;
use Webklex\PHPIMAP\Support\Masks\MessageMask;

/**
 * Class MessageTest.
 */
class MessageTest extends LiveMailboxTestCase
{
    /**
     * Get the default message.
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws ResponseException
     * @throws RuntimeException
     */
    protected function getDefaultMessage(): Message
    {
        $folder = $this->getFolder('INBOX');

        $message = $this->appendMessageTemplate($folder, 'plain.eml');
        self::assertInstanceOf(Message::class, $message);

        return $message;
    }

    /**
     * Test Message::convertEncoding().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws ResponseException
     * @throws RuntimeException
     * @throws MessageNotFoundException
     */
    public function testConvertEncoding(): void
    {
        $message = $this->getDefaultMessage();
        self::assertEquals('Entwürfe+', $message->convertEncoding('Entw&APw-rfe+', 'UTF7-IMAP', 'UTF-8'));

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::thread().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     * @throws GetMessagesFailedException
     */
    public function testThread(): void
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

    /**
     * Test Message::hasAttachments().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testHasAttachments(): void
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

    /**
     * Test Message::getFetchOptions().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testGetFetchOptions(): void
    {
        $message = $this->getDefaultMessage();
        self::assertEquals(IMAP::FT_PEEK, $message->getFetchOptions());

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::getMessageId().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testGetMessageId(): void
    {
        $folder = $this->getFolder('INBOX');
        $message = $this->appendMessageTemplate($folder, 'example_attachment.eml');
        self::assertInstanceOf(Message::class, $message);

        self::assertEquals('d3a5e91963cb805cee975687d5acb1c6@swift.generated', $message->getMessageId());

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::getReplyTo().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testGetReplyTo(): void
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

    /**
     * Test Message::setSequence().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testSetSequence(): void
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

    /**
     * Test Message::getEvent().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testGetEvent(): void
    {
        $message = $this->getDefaultMessage();

        $message->setEvent('message', 'test', 'test');
        self::assertEquals('test', $message->getEvent('message', 'test'));

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::__construct().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function test__construct(): void
    {
        $message = $this->getDefaultMessage();

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::setFlag().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testSetFlag(): void
    {
        $message = $this->getDefaultMessage();

        self::assertTrue($message->setFlag('seen'));
        self::assertTrue($message->getFlags()->has('seen'));

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::getMsgn().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testGetMsgn(): void
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

    /**
     * Test Message::peek().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testPeek(): void
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

    /**
     * Test Message::unsetFlag().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testUnsetFlag(): void
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

    /**
     * Test Message::setSequenceId().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testSetSequenceId(): void
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

    /**
     * Test Message::getTo().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testGetTo(): void
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

    /**
     * Test Message::setUid().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testSetUid(): void
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

    /**
     * Test Message::getUid().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testGetUid(): void
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

    /**
     * Test Message::hasTextBody().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testHasTextBody(): void
    {
        $message = $this->getDefaultMessage();
        self::assertTrue($message->hasTextBody());

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::__get().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function test__get(): void
    {
        $message = $this->getDefaultMessage();
        self::assertEquals($message->uid, $message->getSequenceId());
        self::assertEquals('Example', $message->subject);
        self::assertEquals('to@someone-else.com', $message->to);

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::getDate().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testGetDate(): void
    {
        $message = $this->getDefaultMessage();
        self::assertInstanceOf(Carbon::class, $message->getDate()->toDate());

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::setMask().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testSetMask(): void
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

    /**
     * Test Message::getSequenceId().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testGetSequenceId(): void
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

    /**
     * Test Message::setConfig().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testSetConfig(): void
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

    /**
     * Test Message::getEvents().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testGetEvents(): void
    {
        $message = $this->getDefaultMessage();

        $events = $message->getEvents();
        self::assertIsArray($events);

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::setFetchOption().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testSetFetchOption(): void
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

    /**
     * Test Message::getMsglist().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testGetMsglist(): void
    {
        $message = $this->getDefaultMessage();

        self::assertEquals(0, (int) $message->getMsglist()->toString());

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::decodeString().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testDecodeString(): void
    {
        $message = $this->getDefaultMessage();

        $string = '<p class=3D"MsoNormal">Test<o:p></o:p></p>';
        self::assertEquals('<p class="MsoNormal">Test<o:p></o:p></p>', $message->decodeString($string, IMAP::MESSAGE_ENC_QUOTED_PRINTABLE));

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::attachments().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testAttachments(): void
    {
        $folder = $this->getFolder('INBOX');

        $message = $this->appendMessageTemplate($folder, 'example_attachment.eml');
        self::assertTrue($message->hasAttachments());
        self::assertSameSize([1], $message->attachments());

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::getMask().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testGetMask(): void
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

    /**
     * Test Message::hasHTMLBody().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testHasHTMLBody(): void
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

    /**
     * Test Message::setEvents().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testSetEvents(): void
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

    /**
     * Test Message::__set().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function test__set(): void
    {
        $message = $this->getDefaultMessage();

        $message->foo = 'bar';
        self::assertEquals('bar', $message->getFoo());

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::getHTMLBody().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testGetHTMLBody(): void
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

    /**
     * Test Message::getSequence().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testGetSequence(): void
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

    /**
     * Test Message::restore().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testRestore(): void
    {
        $message = $this->getDefaultMessage();

        $message->setFlag('deleted');
        self::assertTrue($message->hasFlag('deleted'));

        $message->restore();
        self::assertFalse($message->hasFlag('deleted'));

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::getPriority().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testGetPriority(): void
    {
        $folder = $this->getFolder('INBOX');

        $message = $this->appendMessageTemplate($folder, 'example_attachment.eml');
        self::assertEquals(1, $message->getPriority()->first());

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::setAttachments().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testSetAttachments(): void
    {
        $message = $this->getDefaultMessage();

        $message->setAttachments(new AttachmentCollection(['foo' => 'bar']));
        self::assertIsArray($message->attachments()->toArray());
        self::assertTrue($message->attachments()->has('foo'));

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::getFrom().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testGetFrom(): void
    {
        $message = $this->getDefaultMessage();
        self::assertEquals('from@someone.com', $message->getFrom()->first()->mail);

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::setEvent().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testSetEvent(): void
    {
        $message = $this->getDefaultMessage();

        $message->setEvent('message', 'bar', 'foo');
        self::assertArrayHasKey('bar', $message->getEvents()['message']);

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::getInReplyTo().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testGetInReplyTo(): void
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

    /**
     * Test Message::copy().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testCopy(): void
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

    /**
     * Test Message::getBodies().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testGetBodies(): void
    {
        $message = $this->getDefaultMessage();
        self::assertIsArray($message->getBodies());
        self::assertCount(1, $message->getBodies());

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::getFlags().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testGetFlags(): void
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

    /**
     * Test Message::addFlag().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testAddFlag(): void
    {
        $message = $this->getDefaultMessage();
        self::assertFalse($message->hasFlag('seen'));

        self::assertTrue($message->addFlag('seen'));
        self::assertTrue($message->hasFlag('seen'));

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::getSubject().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testGetSubject(): void
    {
        $message = $this->getDefaultMessage();
        self::assertEquals('Example', $message->getSubject());

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::getClient().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testGetClient(): void
    {
        $message = $this->getDefaultMessage();
        self::assertInstanceOf(Client::class, $message->getClient());

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::setFetchFlagsOption().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testSetFetchFlagsOption(): void
    {
        $message = $this->getDefaultMessage();

        self::assertTrue($message->getFetchFlagsOption());
        $message->setFetchFlagsOption(false);
        self::assertFalse($message->getFetchFlagsOption());

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::mask().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testMask(): void
    {
        $message = $this->getDefaultMessage();
        self::assertInstanceOf(MessageMask::class, $message->mask());

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::setMsglist().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testSetMsglist(): void
    {
        $message = $this->getDefaultMessage();
        $message->setMsglist('foo');
        self::assertEquals('foo', $message->getMsglist());

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::flags().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testFlags(): void
    {
        $message = $this->getDefaultMessage();
        self::assertInstanceOf(FlagCollection::class, $message->flags());

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::getAttributes().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testGetAttributes(): void
    {
        $message = $this->getDefaultMessage();
        self::assertIsArray($message->getAttributes());
        self::assertArrayHasKey('subject', $message->getAttributes());

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::getAttachments().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testGetAttachments(): void
    {
        $message = $this->getDefaultMessage();
        self::assertInstanceOf(AttachmentCollection::class, $message->getAttachments());

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::getRawBody().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testGetRawBody(): void
    {
        $message = $this->getDefaultMessage();
        self::assertIsString($message->getRawBody());

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::is().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testIs(): void
    {
        $message = $this->getDefaultMessage();
        self::assertTrue($message->is($message));

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::setFlags().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testSetFlags(): void
    {
        $message = $this->getDefaultMessage();
        $message->setFlags(new FlagCollection());
        self::assertFalse($message->hasFlag('recent'));

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::make().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws ResponseException
     * @throws RuntimeException
     * @throws ReflectionException
     */
    public function testMake(): void
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

    /**
     * Test Message::setAvailableFlags().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testSetAvailableFlags(): void
    {
        $message = $this->getDefaultMessage();

        $message->setAvailableFlags(['foo']);
        self::assertSameSize(['foo'], $message->getAvailableFlags());
        self::assertEquals('foo', $message->getAvailableFlags()[0]);

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::getSender().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testGetSender(): void
    {
        $folder = $this->getFolder('INBOX');

        $message = $this->appendMessageTemplate($folder, 'example_attachment.eml');
        self::assertEquals('testsender <someone@domain.tld>', $message->getSender());
        self::assertEquals('testsender', $message->getSender()->first()->personal);
        self::assertEquals('someone@domain.tld', $message->getSender()->first()->mail);

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::fromFile().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws ReflectionException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testFromFile(): void
    {
        $this->getManager();
        $filename = implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'messages', '1366671050@github.com.eml']);
        $message = Message::fromFile($filename);
        self::assertInstanceOf(Message::class, $message);
    }

    /**
     * Test Message::getStructure().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testGetStructure(): void
    {
        $message = $this->getDefaultMessage();
        self::assertInstanceOf(Structure::class, $message->getStructure());

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::get().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     * @throws MessageSizeFetchingException
     */
    public function testGet(): void
    {
        $message = $this->getDefaultMessage();
        self::assertEquals('Example', $message->get('subject'));

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::getSize().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testGetSize(): void
    {
        $message = $this->getDefaultMessage();
        self::assertEquals(214, $message->getSize());

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::getHeader().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testGetHeader(): void
    {
        $message = $this->getDefaultMessage();
        self::assertInstanceOf(Header::class, $message->getHeader());

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::getReferences().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testGetReferences(): void
    {
        $folder = $this->getFolder('INBOX');

        $message = $this->appendMessageTemplate($folder, '1366671050@github.com.eml');
        self::assertIsArray($message->getReferences()->all());
        self::assertEquals('Webklex/php-imap/issues/349@github.com', $message->getReferences()->first());

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::setFolderPath().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testSetFolderPath(): void
    {
        $message = $this->getDefaultMessage();

        $folder_path = $message->getFolderPath();

        $message->setFolderPath('foo');
        self::assertEquals('foo', $message->getFolderPath());

        $message->setFolderPath($folder_path);

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::getTextBody().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testGetTextBody(): void
    {
        $message = $this->getDefaultMessage();
        self::assertIsString($message->getTextBody());

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::move().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testMove(): void
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

    /**
     * Test Message::getFolderPath().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testGetFolderPath(): void
    {
        $message = $this->getDefaultMessage();
        self::assertEquals('INBOX', $message->getFolderPath());

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::getFolder().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testGetFolder(): void
    {
        $message = $this->getDefaultMessage();
        self::assertInstanceOf(Folder::class, $message->getFolder());

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::getFetchBodyOption().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testGetFetchBodyOption(): void
    {
        $message = $this->getDefaultMessage();
        self::assertTrue($message->getFetchBodyOption());

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::setFetchBodyOption().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testSetFetchBodyOption(): void
    {
        $message = $this->getDefaultMessage();

        $message->setFetchBodyOption(false);
        self::assertFalse($message->getFetchBodyOption());

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::getFetchFlagsOption().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testGetFetchFlagsOption(): void
    {
        $message = $this->getDefaultMessage();
        self::assertTrue($message->getFetchFlagsOption());

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::__call().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function test__call(): void
    {
        $message = $this->getDefaultMessage();
        self::assertEquals('Example', $message->getSubject());

        // Cleanup
        self::assertTrue($message->delete());
    }

    /**
     * Test Message::setClient().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testSetClient(): void
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

    /**
     * Test Message::setMsgn().
     *
     *
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws InvalidMessageDateException
     * @throws MaskNotFoundException
     * @throws MessageContentFetchingException
     * @throws MessageFlagException
     * @throws MessageHeaderFetchingException
     * @throws MessageNotFoundException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function testSetMsgn(): void
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
