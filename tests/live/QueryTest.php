<?php

namespace Tests\live;

use Carbon\Carbon;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Query\WhereQuery;

class QueryTest extends LiveMailboxTestCase
{
    public function test_query(): void
    {
        $folder = $this->getFolder('INBOX');
        $this->assertInstanceOf(Folder::class, $folder);

        $this->assertInstanceOf(WhereQuery::class, $folder->query());
        $this->assertInstanceOf(WhereQuery::class, $folder->search());
        $this->assertInstanceOf(WhereQuery::class, $folder->messages());
    }

    public function test_query_where(): void
    {
        $client = $this->getClient();

        $delimiter = $this->getManager()->get('options.delimiter');
        $folder_path = implode($delimiter, ['INBOX', 'search']);

        $folder = $client->getFolder($folder_path);
        if ($folder !== null) {
            $this->assertTrue($this->deleteFolder($folder));
        }
        $folder = $client->createFolder($folder_path, false);

        $messages = [
            $this->appendMessageTemplate($folder, '1366671050@github.com.eml'),
            $this->appendMessageTemplate($folder, 'attachment_encoded_filename.eml'),
            $this->appendMessageTemplate($folder, 'attachment_long_filename.eml'),
            $this->appendMessageTemplate($folder, 'attachment_no_disposition.eml'),
            $this->appendMessageTemplate($folder, 'bcc.eml'),
            $this->appendMessageTemplate($folder, 'boolean_decoded_content.eml'),
            $this->appendMessageTemplate($folder, 'email_address.eml'),
            $this->appendMessageTemplate($folder, 'embedded_email.eml'),
            $this->appendMessageTemplate($folder, 'embedded_email_without_content_disposition.eml'),
            $this->appendMessageTemplate($folder, 'embedded_email_without_content_disposition-embedded.eml'),
            $this->appendMessageTemplate($folder, 'example_attachment.eml'),
            $this->appendMessageTemplate($folder, 'example_bounce.eml'),
            $this->appendMessageTemplate($folder, 'four_nested_emails.eml'),
            $this->appendMessageTemplate($folder, 'gbk_charset.eml'),
            $this->appendMessageTemplate($folder, 'html_only.eml'),
            $this->appendMessageTemplate($folder, 'imap_mime_header_decode_returns_false.eml'),
            $this->appendMessageTemplate($folder, 'inline_attachment.eml'),
            $this->appendMessageTemplate($folder, 'issue-275.eml'),
            $this->appendMessageTemplate($folder, 'issue-275-2.eml'),
            $this->appendMessageTemplate($folder, 'issue-348.eml'),
            $this->appendMessageTemplate($folder, 'ks_c_5601-1987_headers.eml'),
            $this->appendMessageTemplate($folder, 'mail_that_is_attachment.eml'),
            $this->appendMessageTemplate($folder, 'missing_date.eml'),
            $this->appendMessageTemplate($folder, 'missing_from.eml'),
            $this->appendMessageTemplate($folder, 'mixed_filename.eml'),
            $this->appendMessageTemplate($folder, 'multipart_without_body.eml'),
            $this->appendMessageTemplate($folder, 'multiple_html_parts_and_attachments.eml'),
            $this->appendMessageTemplate($folder, 'multiple_nested_attachments.eml'),
            $this->appendMessageTemplate($folder, 'nestes_embedded_with_attachment.eml'),
            $this->appendMessageTemplate($folder, 'null_content_charset.eml'),
            $this->appendMessageTemplate($folder, 'pec.eml'),
            $this->appendMessageTemplate($folder, 'plain.eml'),
            $this->appendMessageTemplate($folder, 'plain_only.eml'),
            $this->appendMessageTemplate($folder, 'plain_text_attachment.eml'),
            $this->appendMessageTemplate($folder, 'references.eml'),
            $this->appendMessageTemplate($folder, 'simple_multipart.eml'),
            $this->appendMessageTemplate($folder, 'structured_with_attachment.eml'),
            $this->appendMessageTemplate($folder, 'thread_my_topic.eml'),
            $this->appendMessageTemplate($folder, 'thread_re_my_topic.eml'),
            $this->appendMessageTemplate($folder, 'thread_unrelated.eml'),
            $this->appendMessageTemplate($folder, 'undefined_charset_header.eml'),
            $this->appendMessageTemplate($folder, 'undisclosed_recipients_minus.eml'),
            $this->appendMessageTemplate($folder, 'undisclosed_recipients_space.eml'),
            $this->appendMessageTemplate($folder, 'unknown_encoding.eml'),
            $this->appendMessageTemplate($folder, 'without_charset_plain_only.eml'),
            $this->appendMessageTemplate($folder, 'without_charset_simple_multipart.eml'),
        ];

        $folder->getClient()->expunge();

        $query = $folder->query()->all();
        $this->assertEquals(count($messages), $query->count());

        $query = $folder->query()->whereSubject('test');
        $this->assertEquals(11, $query->count());

        $query = $folder->query()->whereOn(Carbon::now());
        $this->assertEquals(count($messages), $query->count());

        $this->assertTrue($this->deleteFolder($folder));
    }

    public function test_query_where_criteria(): void
    {
        $folder = $this->getFolder('INBOX');
        $this->assertInstanceOf(Folder::class, $folder);

        $this->assertWhereSearchCriteria($folder, 'SUBJECT', 'Test');
        $this->assertWhereSearchCriteria($folder, 'BODY', 'Test');
        $this->assertWhereSearchCriteria($folder, 'TEXT', 'Test');
        $this->assertWhereSearchCriteria($folder, 'KEYWORD', 'Test');
        $this->assertWhereSearchCriteria($folder, 'UNKEYWORD', 'Test');
        $this->assertWhereSearchCriteria($folder, 'FLAGGED', 'Seen');
        $this->assertWhereSearchCriteria($folder, 'UNFLAGGED', 'Seen');
        $this->assertHeaderSearchCriteria($folder, 'Message-ID', 'Seen');
        $this->assertHeaderSearchCriteria($folder, 'In-Reply-To', 'Seen');
        $this->assertWhereSearchCriteria($folder, 'BCC', 'test@example.com');
        $this->assertWhereSearchCriteria($folder, 'CC', 'test@example.com');
        $this->assertWhereSearchCriteria($folder, 'FROM', 'test@example.com');
        $this->assertWhereSearchCriteria($folder, 'TO', 'test@example.com');
        $this->assertWhereSearchCriteria($folder, 'UID', '1');
        $this->assertWhereSearchCriteria($folder, 'UID', '1,2');
        $this->assertWhereSearchCriteria($folder, 'ALL');
        $this->assertWhereSearchCriteria($folder, 'NEW');
        $this->assertWhereSearchCriteria($folder, 'OLD');
        $this->assertWhereSearchCriteria($folder, 'SEEN');
        $this->assertWhereSearchCriteria($folder, 'UNSEEN');
        $this->assertWhereSearchCriteria($folder, 'RECENT');
        $this->assertWhereSearchCriteria($folder, 'ANSWERED');
        $this->assertWhereSearchCriteria($folder, 'UNANSWERED');
        $this->assertWhereSearchCriteria($folder, 'DELETED');
        $this->assertWhereSearchCriteria($folder, 'UNDELETED');
        $this->assertHeaderSearchCriteria($folder, 'Content-Language', 'en_US');
        $this->assertWhereSearchCriteria($folder, 'CUSTOM X-Spam-Flag NO');
        $this->assertWhereSearchCriteria($folder, 'CUSTOM X-Spam-Flag YES');
        $this->assertWhereSearchCriteria($folder, 'NOT');
        $this->assertWhereSearchCriteria($folder, 'OR');
        $this->assertWhereSearchCriteria($folder, 'AND');
        $this->assertWhereSearchCriteria($folder, 'BEFORE', '01-Jan-2020', true);
        $this->assertWhereSearchCriteria($folder, 'BEFORE', Carbon::now()->subDays(), true);
        $this->assertWhereSearchCriteria($folder, 'ON', '01-Jan-2020', true);
        $this->assertWhereSearchCriteria($folder, 'ON', Carbon::now()->subDays(), true);
        $this->assertWhereSearchCriteria($folder, 'SINCE', '01-Jan-2020', true);
        $this->assertWhereSearchCriteria($folder, 'SINCE', Carbon::now()->subDays(), true);
    }

    protected function assertWhereSearchCriteria(Folder $folder, string $criteria, Carbon|string|null $value = null, bool $date = false): void
    {
        $query = $folder->query()->where($criteria, $value);
        $this->assertInstanceOf(WhereQuery::class, $query);

        $item = $query->getQuery()->first();
        $criteria = str_replace('CUSTOM ', '', $criteria);
        $expected = $value === null ? [$criteria] : [$criteria, $value];
        if ($date === true && $value instanceof Carbon) {
            $date_format = ClientManager::get('date_format', 'd M y');
            $expected[1] = $value->format($date_format);
        }

        $this->assertIsArray($item);
        $this->assertIsString($item[0]);
        if ($value !== null) {
            $this->assertCount(2, $item);
            $this->assertIsString($item[1]);
        } else {
            $this->assertCount(1, $item);
        }
        $this->assertSame($expected, $item);
    }

    protected function assertHeaderSearchCriteria(Folder $folder, string $criteria, mixed $value = null): void
    {
        $query = $folder->query()->whereHeader($criteria, $value);
        $this->assertInstanceOf(WhereQuery::class, $query);

        $item = $query->getQuery()->first();

        $this->assertIsArray($item);
        $this->assertIsString($item[0]);
        $this->assertCount(1, $item);
        $this->assertSame(['HEADER '.$criteria.' '.$value], $item);
    }
}
