<?php

namespace Tests\Unit;

use Tests\TestCase;
use Webklex\PHPIMAP\Connection\FakeStream;
use Webklex\PHPIMAP\Connection\ImapConnection;
use Webklex\PHPIMAP\Connection\Response;
use Webklex\PHPIMAP\Exceptions\AuthFailedException;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;
use Webklex\PHPIMAP\Exceptions\ImapServerErrorException;
use Webklex\PHPIMAP\Exceptions\MessageNotFoundException;
use Webklex\PHPIMAP\Exceptions\RuntimeException;

class ImapConnectionTest extends TestCase
{
    public function test_connect_success()
    {
        $stream = new FakeStream;

        $stream->feed('* OK Welcome to IMAP');

        $connection = new ImapConnection($stream);

        $this->assertFalse($connection->connected());

        $connection->connect('imap.example.com', 143);

        $this->assertTrue($connection->connected());
    }

    public function test_connect_failure()
    {
        $stream = new FakeStream;

        $stream->open();

        $stream->feed('BAD');

        $connection = new ImapConnection($stream);

        $this->expectException(ConnectionFailedException::class);

        $connection->connect('imap.example.com', 143);
    }

    public function test_imap_cert_validation()
    {
        $protocol = new ImapConnection;

        $this->assertSame(true, $protocol->getCertValidation());

        $protocol->setCertValidation(false);

        $this->assertSame(false, $protocol->getCertValidation());
    }

    public function test_imap_encryption()
    {
        $protocol = new ImapConnection;

        $this->assertNull($protocol->getEncryption());

        $protocol->setEncryption('ssl');

        $this->assertSame('ssl', $protocol->getEncryption());
    }

    public function test_next_line_reads_line_successfully()
    {
        $fixture = '* OK IMAP4rev1 Service Ready';

        $stream = new FakeStream;

        $stream->open();

        $stream->feed($fixture);

        $protocol = new ImapConnection($stream);

        $response = new Response;

        $line = $protocol->nextLine($response);

        $this->assertSame('* OK IMAP4rev1 Service Ready', $line);
        $this->assertCount(1, $response->getResponse());
        $this->assertSame($fixture, implode('', $response->getResponse()));
    }

    public function test_next_line_multi_line_fixture()
    {
        $stream = new FakeStream;

        $stream->open();

        $stream->feed([
            '* OK Dovecot ready.',
            '* CAPABILITY IMAP4rev1 UIDPLUS',
            '1 OK CAPABILITY completed',
        ]);

        $connection = new ImapConnection($stream);

        $response = new Response;

        $line1 = $connection->nextLine($response);
        $this->assertSame('* OK Dovecot ready.', $line1);

        $line2 = $connection->nextLine($response);
        $this->assertSame('* CAPABILITY IMAP4rev1 UIDPLUS', $line2);

        $line3 = $connection->nextLine($response);
        $this->assertSame('1 OK CAPABILITY completed', $line3);

        $this->assertCount(3, $response->getResponse());
    }

    public function test_done()
    {
        $stream = new FakeStream;

        $stream->open();

        $stream->feed(['* OK Completed']);

        $connection = new ImapConnection($stream);

        $connection->done();

        $stream->assertWritten('DONE');
    }

    public function test_done_with_failed_response()
    {
        $stream = new FakeStream;

        $stream->open();

        $stream->feed('BAD');

        $connection = new ImapConnection($stream);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Done failed');

        $connection->done();
    }

    public function test_idle()
    {
        $stream = new FakeStream;

        $stream->open();

        $stream->feed('+ idling');

        $connection = new ImapConnection($stream);

        $connection->idle();

        $stream->assertWritten('TAG1 IDLE');
    }

    public function test_idle_with_multi_line_response()
    {
        $stream = new FakeStream;

        $stream->open();

        $stream->feed([
            '* OK Still here',
            'TAG1 OK',
            '+ idling',
        ]);

        $connection = new ImapConnection($stream);

        $connection->idle();

        $stream->assertWritten('TAG1 IDLE');
    }

    public function test_idle_with_failed_response()
    {
        $stream = new FakeStream;

        $stream->open();

        $stream->feed('BAD');

        $connection = new ImapConnection($stream);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Idle failed. Unexpected response: BAD');

        $connection->idle();
    }

    public function test_noop()
    {
        $stream = new FakeStream;

        $stream->open();

        $stream->feed('TAG1 OK NOOP completed');

        $connection = new ImapConnection($stream);

        $response = $connection->noop();

        $stream->assertWritten('TAG1 NOOP');

        $this->assertSame(['TAG1 OK NOOP completed'], $response->getResponse());
    }

    public function test_get_capabilities()
    {
        $stream = new FakeStream;

        $stream->open();

        $stream->feed([
            '* CAPABILITY IMAP4rev1 UNSELECT IDLE NAMESPACE',
            'TAG1 OK CAPABILITY completed',
        ]);

        $connection = new ImapConnection($stream);

        $response = $connection->getCapabilities();

        $stream->assertWritten('TAG1 CAPABILITY');

        $this->assertSame([
            '* CAPABILITY IMAP4rev1 UNSELECT IDLE NAMESPACE',
            'TAG1 OK CAPABILITY completed',
        ], $response->getResponse());

        $this->assertEquals([
            'CAPABILITY',
            'IMAP4rev1',
            'UNSELECT',
            'IDLE',
            'NAMESPACE',
        ], $response->getValidatedData());

    }

    public function test_get_capabilities_failure()
    {
        $stream = new FakeStream;

        $stream->open();

        $stream->feed('BAD');

        $connection = new ImapConnection($stream);

        $this->expectException(RuntimeException::class);

        $connection->getCapabilities();
    }

    public function test_connect()
    {
        $stream = new FakeStream;

        $stream->open();

        $stream->feed('* OK IMAP4rev1 Service Ready');

        $connection = new ImapConnection($stream);

        $connection->connect('imap.example.com', 143);

        $this->assertTrue($connection->connected());
    }

    public function test_login_success()
    {
        $stream = new FakeStream;

        $stream->open();

        $stream->feed('TAG1 OK LOGIN done');

        $connection = new ImapConnection($stream);

        $response = $connection->login('user', 'pass');

        $stream->assertWritten('TAG1 LOGIN "user" "pass"');

        $this->assertSame(['TAG1 OK LOGIN done'], $response->getResponse());
    }

    public function test_login_failure()
    {
        $stream = new FakeStream;

        $stream->open();

        $stream->feed([
            'TAG1 NO [AUTHENTICATIONFAILED] Authentication failed.',
        ]);

        $connection = new ImapConnection($stream);

        $this->expectException(ImapServerErrorException::class);

        $connection->login('user', 'invalid');
    }

    public function test_authenticate_success()
    {
        $stream = new FakeStream;

        $stream->open();

        // Simulate a typical multi-line XOAUTH2 handshake
        // The server will eventually respond with '+', meaning "send next part"
        $stream->feed([
            '+ ',
            'TAG1 OK Authentication succeeded',
        ]);

        $connection = new ImapConnection($stream);

        $response = $connection->authenticate('user@example.com', 'fake_token');

        $stream->assertWritten('TAG1 AUTHENTICATE XOAUTH2');

        $this->assertSame([
            '+ ',
            'TAG1 OK Authentication succeeded',
        ], $response->getResponse());
    }

    public function test_authenticate_failure()
    {
        $stream = new FakeStream;

        $stream->open();

        // The server responds with a negative code after AUTHENTICATE:
        $stream->feed('BAD');

        $connection = new ImapConnection($stream);

        $this->expectException(AuthFailedException::class);

        $connection->authenticate('user@example.com', 'bad_token');
    }

    public function test_logout()
    {
        $stream = new FakeStream;

        $stream->open();

        $stream->feed([
            '* BYE Logging out',
            'TAG1 OK LOGOUT completed',
        ]);

        $connection = new ImapConnection($stream);

        $response = $connection->logout();

        $stream->assertWritten('TAG1 LOGOUT');

        $this->assertSame([
            '* BYE Logging out',
            'TAG1 OK LOGOUT completed',
        ], $response->getResponse());
    }

    public function test_logout_when_already_closed()
    {
        $stream = new FakeStream;

        $stream->feed('* OK IMAP4rev1 Service Ready');

        $connection = new ImapConnection($stream);
        $connection->connect('imap.example.com', 143);

        $stream->close(); // Forcibly close.

        // This should silently fail without throwing an exception.
        $response = $connection->logout();

        $this->assertEmpty($response->getResponse());
        $this->assertFalse($connection->connected());
    }

    public function test_select_folder()
    {
        $stream = new FakeStream;

        $stream->open();

        // Provide a typical SELECT response.
        $stream->feed([
            '* 23 EXISTS',
            '* 2 RECENT',
            '* OK [UIDVALIDITY 3857529045] UIDs valid',
            '* OK [UIDNEXT 4392] Predicted next UID',
            'TAG1 OK [READ-WRITE] SELECT completed',
        ]);

        $connection = new ImapConnection($stream);

        $response = $connection->selectFolder('INBOX');

        $stream->assertWritten('TAG1 SELECT "INBOX"');

        $this->assertStringContainsString('OK [READ-WRITE] SELECT completed', implode("\n", $response->getResponse()));
        $this->assertIsArray($response->data());
        $this->assertEquals(23, $response->data()['exists'] ?? null);
    }

    public function test_examine_folder()
    {
        $stream = new FakeStream;

        $stream->open();

        $stream->feed([
            '* 23 EXISTS',
            '* 2 RECENT',
            'TAG1 OK [READ-ONLY] EXAMINE completed',
        ]);

        $connection = new ImapConnection($stream);

        $response = $connection->examineFolder('INBOX');

        $stream->assertWritten('TAG1 EXAMINE "INBOX"');
        $this->assertEquals(23, $response->data()['exists'] ?? null);
        $this->assertEquals(2, $response->data()['recent'] ?? null);
    }

    public function test_folder_status()
    {
        $stream = new FakeStream;

        $stream->open();

        $stream->feed([
            '* STATUS "INBOX" (MESSAGES 42 UNSEEN 3 RECENT 2 UIDNEXT 66 UIDVALIDITY 1)',
            'TAG1 OK STATUS completed',
        ]);

        $connection = new ImapConnection($stream);

        $response = $connection->folderStatus('INBOX');

        $stream->assertWritten('TAG1 STATUS "INBOX" (MESSAGES UNSEEN RECENT UIDNEXT UIDVALIDITY)');
        $this->assertEquals(42, $response->data()['messages'] ?? null);
        $this->assertEquals(3, $response->data()['unseen'] ?? null);
        $this->assertEquals(2, $response->data()['recent'] ?? null);
        $this->assertEquals(66, $response->data()['uidnext'] ?? null);
        $this->assertEquals(1, $response->data()['uidvalidity'] ?? null);
    }

    public function test_fetch_single()
    {
        $stream = new FakeStream;

        $stream->open();

        $stream->feed([
            '* 1 FETCH (UID 1 BODY[] {12}',
            'Hello world!',
            ')',
            'TAG1 OK FETCH completed',
        ]);

        $connection = new ImapConnection($stream);

        $response = $connection->fetch('BODY[]', [1]);

        $stream->assertWritten('TAG1 UID FETCH 1:1 (BODY[])');

        $this->assertStringContainsString('Hello world!', $response->data()[1]);
    }

    public function test_fetch_range()
    {
        $stream = new FakeStream;

        $stream->open();

        $stream->feed([
            '* 1 FETCH (UID 1 BODY[] {13}',
            'Message #1...',
            ')',
            '* 2 FETCH (UID 2 BODY[] {13}',
            'Message #2...',
            ')',
            'TAG1 OK FETCH completed',
        ]);

        $connection = new ImapConnection($stream);

        // Fetch messages 1-2.
        $response = $connection->fetch('BODY[]', 1, 2);

        $stream->assertWritten('TAG1 UID FETCH 1:2 (BODY[])');

        $result = $response->data();

        $this->assertCount(2, $result);

        $this->assertArrayHasKey(1, $result);
        $this->assertArrayHasKey(2, $result);
    }

    public function test_content()
    {
        $stream = new FakeStream;

        $stream->open();

        $stream->feed([
            '* 1 FETCH (UID 999 RFC822.TEXT {18}',
            'message content...',
            ')',
            'TAG1 OK FETCH completed',
        ]);

        $connection = new ImapConnection($stream);

        $response = $connection->content(999);

        $stream->assertWritten('TAG1 UID FETCH 999:999 (RFC822.TEXT)');

        $this->assertStringContainsString('message content...', $response->data()[999]);
    }

    public function test_headers()
    {
        $stream = new FakeStream;

        $stream->open();

        $stream->feed([
            '* 1 FETCH (UID 777 RFC822.HEADER {13}',
            'Subject: Test',
            ')',
            'TAG1 OK FETCH completed',
        ]);

        $connection = new ImapConnection($stream);

        $response = $connection->headers(777);

        $stream->assertWritten('TAG1 UID FETCH 777:777 (RFC822.HEADER)');

        $this->assertStringContainsString('Subject: Test', $response->data()[777]);
    }

    public function test_flags()
    {
        $stream = new FakeStream;

        $stream->open();

        $stream->feed([
            '* 1 FETCH (UID 3 FLAGS (\Seen))',
            'TAG1 OK FETCH completed',
        ]);

        $connection = new ImapConnection($stream);

        $response = $connection->flags(3);

        $stream->assertWritten('TAG1 UID FETCH 3:3 (FLAGS)');

        $this->assertEquals(['\\Seen'], $response->data()[3]);
    }

    public function test_sizes()
    {
        $stream = new FakeStream;

        $stream->open();

        $stream->feed([
            '* 1 FETCH (UID 4 RFC822.SIZE 12345)',
            'TAG1 OK FETCH completed',
        ]);

        $connection = new ImapConnection($stream);

        $response = $connection->sizes(4);

        $stream->assertWritten('TAG1 UID FETCH 4:4 (RFC822.SIZE)');

        $this->assertEquals(12345, $response->data()[4]);
    }

    public function test_folders()
    {
        $stream = new FakeStream;

        $stream->open();

        $stream->feed([
            '* LIST (\\HasNoChildren) "." "INBOX"',
            '* LIST (\\HasChildren) "." "Archive"',
            'TAG1 OK LIST completed',
        ]);

        $connection = new ImapConnection($stream);

        $response = $connection->folders('', '*');

        $stream->assertWritten('TAG1 LIST "" "*"');
        $this->assertArrayHasKey('INBOX', $response->data());
        $this->assertArrayHasKey('Archive', $response->data());
    }

    public function test_store_add_flag()
    {
        $stream = new FakeStream;

        $stream->open();

        // Non-silent store typically returns updated flags:
        $stream->feed([
            '* 5 FETCH (FLAGS (\Seen \\Flagged))',
            'TAG1 OK STORE completed',
        ]);

        $connection = new ImapConnection($stream);

        $response = $connection->store(['\\Flagged'], 5, null, '+', false);

        $stream->assertWritten('TAG1 UID STORE 5 +FLAGS (\Flagged)');

        $this->assertEquals(['\\Seen', '\\Flagged'], $response->data()[5]);
    }

    public function test_append_message()
    {
        $stream = new FakeStream;

        $stream->open();

        $stream->feed([
            'TAG1 OK APPEND completed',
        ]);

        $connection = new ImapConnection($stream);

        $response = $connection->appendMessage('INBOX', 'Raw message data', ['\\Seen'], '12-Jun-2023 12:00:00 +0000');

        $stream->assertWritten('TAG1 APPEND "INBOX" (\Seen) "12-Jun-2023 12:00:00 +0000" "Raw message data"');
        $this->assertEquals(['TAG1 OK APPEND completed'], $response->getResponse());
    }

    public function test_copy_message()
    {
        $stream = new FakeStream;

        $stream->open();

        $stream->feed([
            'TAG1 OK COPY completed',
        ]);

        $connection = new ImapConnection($stream);

        $response = $connection->copyMessage('Archive', 7);

        $stream->assertWritten('TAG1 UID COPY 7 "Archive"');
        $this->assertEquals(['TAG1 OK COPY completed'], $response->getResponse());
    }

    public function test_copy_many_messages()
    {
        $stream = new FakeStream;

        $stream->open();

        $stream->feed([
            'TAG1 OK COPY completed',
        ]);

        $connection = new ImapConnection($stream);

        $response = $connection->copyManyMessages([1, 2, 3], 'Archive');

        $stream->assertWritten('TAG1 UID COPY 1,2,3 "Archive"');
        $this->assertEquals(['TAG1 OK COPY completed'], $response->getResponse());
    }

    public function test_move_message()
    {
        $stream = new FakeStream;

        $stream->open();

        $stream->feed([
            'TAG1 OK MOVE completed',
        ]);

        $connection = new ImapConnection($stream);

        $response = $connection->moveMessage('Trash', 10);

        $stream->assertWritten('TAG1 UID MOVE 10 "Trash"');
        $this->assertEquals(['TAG1 OK MOVE completed'], $response->getResponse());
    }

    public function test_move_many_messages()
    {
        $stream = new FakeStream;

        $stream->open();

        $stream->feed([
            'TAG1 OK MOVE completed',
        ]);

        $connection = new ImapConnection($stream);

        $response = $connection->moveManyMessages([10, 11, 12], 'Trash');

        $stream->assertWritten('TAG1 UID MOVE 10,11,12 "Trash"');
        $this->assertEquals(['TAG1 OK MOVE completed'], $response->getResponse());
    }

    public function test_id_command()
    {
        $stream = new FakeStream;

        $stream->open();

        $stream->feed([
            '* ID ("name" "Dovecot")',
            'TAG1 OK ID completed',
        ]);

        $connection = new ImapConnection($stream);

        // Provide some client data
        $response = $connection->id(['name', 'MyClient']);

        $stream->assertWritten('TAG1 ID ("name" "MyClient")');
        $this->assertEquals(['* ID ("name" "Dovecot")', 'TAG1 OK ID completed'], $response->getResponse());
    }

    public function test_create_folder()
    {
        $stream = new FakeStream;

        $stream->open();

        $stream->feed([
            'TAG1 OK CREATE completed',
        ]);

        $connection = new ImapConnection($stream);

        $response = $connection->createFolder('NewFolder');

        $stream->assertWritten('TAG1 CREATE "NewFolder"');
        $this->assertEquals(['TAG1 OK CREATE completed'], $response->getResponse());
    }

    public function test_rename_folder()
    {
        $stream = new FakeStream;

        $stream->open();

        $stream->feed([
            'TAG1 OK RENAME completed',
        ]);

        $connection = new ImapConnection($stream);

        $response = $connection->renameFolder('OldFolder', 'RenamedFolder');

        $stream->assertWritten('TAG1 RENAME "OldFolder" "RenamedFolder"');
        $this->assertEquals(['TAG1 OK RENAME completed'], $response->getResponse());
    }

    public function test_delete_folder()
    {
        $stream = new FakeStream;

        $stream->open();

        $stream->feed([
            'TAG1 OK DELETE completed',
        ]);

        $connection = new ImapConnection($stream);

        $response = $connection->deleteFolder('Trash');

        $stream->assertWritten('TAG1 DELETE "Trash"');
        $this->assertEquals(['TAG1 OK DELETE completed'], $response->getResponse());
    }

    public function test_subscribe_folder()
    {
        $stream = new FakeStream;

        $stream->open();

        $stream->feed([
            'TAG1 OK SUBSCRIBE completed',
        ]);

        $connection = new ImapConnection($stream);

        $response = $connection->subscribeFolder('Newsletters');

        $stream->assertWritten('TAG1 SUBSCRIBE "Newsletters"');
        $this->assertEquals(['TAG1 OK SUBSCRIBE completed'], $response->getResponse());
    }

    public function test_unsubscribe_folder()
    {
        $stream = new FakeStream;

        $stream->open();

        $stream->feed([
            'TAG1 OK UNSUBSCRIBE completed',
        ]);

        $connection = new ImapConnection($stream);

        $response = $connection->unsubscribeFolder('Newsletters');

        $stream->assertWritten('TAG1 UNSUBSCRIBE "Newsletters"');
        $this->assertEquals(['TAG1 OK UNSUBSCRIBE completed'], $response->getResponse());
    }

    public function test_expunge()
    {
        $stream = new FakeStream;

        $stream->open();

        $stream->feed([
            '* 1 EXPUNGE',
            '* 2 EXPUNGE',
            'TAG1 OK EXPUNGE completed',
        ]);

        $connection = new ImapConnection($stream);

        $response = $connection->expunge();

        $stream->assertWritten('TAG1 EXPUNGE');

        $this->assertEquals([
            '* 1 EXPUNGE',
            '* 2 EXPUNGE',
            'TAG1 OK EXPUNGE completed',
        ], $response->getResponse());
    }

    public function test_search()
    {
        $stream = new FakeStream;

        $stream->open();

        // Searching might return multiple IDs in a single line.
        $stream->feed([
            '* SEARCH 3 5 7 8',
            'TAG1 OK SEARCH completed',
        ]);

        $connection = new ImapConnection($stream);

        // For example: Searching "ALL", or "FROM", etc.
        $response = $connection->search(['ALL']);

        $stream->assertWritten('TAG1 UID SEARCH ALL');
        $this->assertEquals([3, 5, 7, 8], $response->data());
    }

    public function test_overview()
    {
        $stream = new FakeStream;

        $stream->open();

        // Simulate fetching UIDs for messages 1 and 2
        $stream->feed([
            '* 1 FETCH (UID 101)',
            '* 2 FETCH (UID 102)',

            'TAG1 OK FETCH completed',

            '* 3 FETCH (UID 101 RFC822.HEADER {12}',
            'Subject: Foo',
            ')',
            '* 4 FETCH (UID 102 RFC822.HEADER {12}',
            'Subject: Bar',
            ')',

            'TAG2 OK FETCH completed',
        ]);

        $connection = new ImapConnection($stream);

        $response = $connection->overview('101:102');

        $stream->assertWritten('TAG1 UID FETCH 1:* (UID)');

        $results = $response->data();

        $this->assertCount(2, $results);
        $this->assertEquals('Foo', $results[101]['subject']);
        $this->assertEquals('Bar', $results[102]['subject']);
    }

    public function test_get_quota()
    {
        $stream = new FakeStream;

        $stream->open();

        $stream->feed([
            '* QUOTA "#user/testuser" (STORAGE 512 1024)',
            'TAG1 OK GETQUOTA completed',
        ]);

        $connection = new ImapConnection($stream);

        $response = $connection->getQuota('testuser');

        $stream->assertWritten('TAG1 GETQUOTA "#user/testuser"');

        $this->assertEquals([
            '* QUOTA "#user/testuser" (STORAGE 512 1024)',
            'TAG1 OK GETQUOTA completed',
        ], $response->getResponse());
    }

    public function test_get_quota_root()
    {
        $stream = new FakeStream;

        $stream->open();

        $stream->feed([
            '* QUOTAROOT INBOX "#user/testuser"',
            '* QUOTA "#user/testuser" (STORAGE 512 1024)',
            'TAG1 OK GETQUOTAROOT completed',
        ]);

        $connection = new ImapConnection($stream);

        $response = $connection->getQuotaRoot('INBOX');

        $stream->assertWritten('TAG1 GETQUOTAROOT INBOX');

        $this->assertStringContainsString('QUOTAROOT INBOX', implode("\n", $response->getResponse()));
    }

    public function test_get_uid()
    {
        $stream = new FakeStream;

        $stream->open();

        // The fetch() call in getUid() tries to get all UIDs from 1:*
        $stream->feed([
            '* 1 FETCH (UID 101)',
            '* 2 FETCH (UID 102)',
            '* 3 FETCH (UID 103)',
            'TAG1 OK FETCH completed',
        ]);

        $connection = new ImapConnection($stream);

        $this->assertEquals([
            1 => 101,
            2 => 102,
            3 => 103,
        ], $connection->getUid()->data());

        $this->assertEquals(102, $connection->getUid(2)->data());
    }

    public function test_get_uid_not_found_throws()
    {
        $stream = new FakeStream;

        $stream->open();

        $stream->feed([
            '* 1 FETCH (UID 101)',
            '* 2 FETCH (UID 102)',
            'TAG1 OK FETCH completed',
        ]);

        $connection = new ImapConnection($stream);

        $this->expectException(MessageNotFoundException::class);
        $connection->getUid(999);
    }

    public function test_get_message_number()
    {
        $stream = new FakeStream;

        $stream->open();

        // getUid() is called first:
        $stream->feed([
            '* 1 FETCH (UID 5000)',
            '* 2 FETCH (UID 5001)',
            'TAG1 OK FETCH completed',
        ]);

        $connection = new ImapConnection($stream);

        $response = $connection->getMessageNumber('5001');

        $this->assertEquals(2, $response->data());
    }

    public function test_get_message_number_not_found_throws()
    {
        $stream = new FakeStream;

        $stream->open();

        // Provide only one UID
        $stream->feed([
            '* 1 FETCH (UID 100)',
            'TAG1 OK FETCH completed',
        ]);

        $connection = new ImapConnection($stream);

        $this->expectException(MessageNotFoundException::class);

        $connection->getMessageNumber('999');
    }

    public function test_build_set()
    {
        $connection = new ImapConnection;

        $this->assertEquals('5:10', $connection->buildSet(5, 10));
        $this->assertEquals('5:*', $connection->buildSet(5, INF));
        $this->assertEquals(5, $connection->buildSet(5));
    }
}
