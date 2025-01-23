<?php

namespace Tests;

use Webklex\PHPIMAP\Header;
use Webklex\PHPIMAP\Structure;

class StructureTest extends TestCase
{
    public function test_structure_parsing(): void
    {
        $email = file_get_contents(implode(DIRECTORY_SEPARATOR, [__DIR__, 'messages', '1366671050@github.com.eml']));

        if (! str_contains($email, "\r\n")) {
            $email = str_replace("\n", "\r\n", $email);
        }

        $raw_header = substr($email, 0, strpos($email, "\r\n\r\n"));
        $raw_body = substr($email, strlen($raw_header) + 8);

        $header = new Header($raw_header);
        $structure = new Structure($raw_body, $header);

        $this->assertSame(2, count($structure->parts));

        $textPart = $structure->parts[0];

        $this->assertSame('UTF-8', $textPart->charset);
        $this->assertSame('text/plain', $textPart->contentType);
        $this->assertSame(278, $textPart->bytes);

        $htmlPart = $structure->parts[1];

        $this->assertSame('UTF-8', $htmlPart->charset);
        $this->assertSame('text/html', $htmlPart->contentType);
        $this->assertSame(1478, $htmlPart->bytes);
    }
}
