<?php

namespace Tests\Fixture;

class AttachmentTest extends FixtureTestCase
{
    /**
     * @dataProvider decodeNameDataProvider
     */
    public function test_decode_name(string $input, string $output): void
    {
        $message = $this->getFixture('attachment_encoded_filename.eml');

        $attachment = $message->getAttachments()->first();

        $name = $attachment->decodeName($input);

        $this->assertEquals($output, $name);
    }

    public function decodeNameDataProvider(): array
    {
        return [
            ['../../../../../../../../../../../var/www/shell.php', '.varwwwshell.php'],
            ['test..xml', 'test.xml'],
            [chr(0), ''],
            ['C:\\file.txt', 'Cfile.txt'],
        ];
    }
}
