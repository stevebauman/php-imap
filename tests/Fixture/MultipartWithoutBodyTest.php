<?php

namespace Tests\Fixture;

class MultipartWithoutBodyTest extends FixtureTestCase
{
    public function test_fixture(): void
    {
        $message = $this->getMessageFixture('multipart_without_body.eml');

        $this->assertEquals('This mail will not contain a body', $message->subject);
        $this->assertEquals('This mail will not contain a body', $message->getTextBody());
        $this->assertEquals('d76dfb1ff3231e3efe1675c971ce73f722b906cc049d328db0d255f8d3f65568', hash('sha256', $message->getHTMLBody()));
        $this->assertEquals('2023-03-11 08:24:31', $message->date->first()->setTimezone('UTC')->format('Y-m-d H:i:s'));
        $this->assertEquals('Foo Bülow Bar <from@example.com>', $message->from);
        $this->assertEquals('some one <someone@somewhere.com>', $message->to);
        $this->assertEquals([
            0 => 'from AS8PR02MB6805.eurprd02.prod.outlook.com (2603:10a6:20b:252::8) by PA4PR02MB7071.eurprd02.prod.outlook.com with HTTPS; Sat, 11 Mar 2023 08:24:33 +0000',
            1 => 'from omef0ahNgeoJu.eurprd02.prod.outlook.com (2603:10a6:10:33c::12) by AS8PR02MB6805.eurprd02.prod.outlook.com (2603:10a6:20b:252::8) with Microsoft SMTP Server (version=TLS1_2, cipher=TLS_ECDHE_RSA_WITH_AES_256_GCM_SHA384) id 15.20.6178.19; Sat, 11 Mar 2023 08:24:31 +0000',
            2 => 'from omef0ahNgeoJu.eurprd02.prod.outlook.com ([fe80::38c0:9c40:7fc6:93a7]) by omef0ahNgeoJu.eurprd02.prod.outlook.com ([fe80::38c0:9c40:7fc6:93a7%7]) with mapi id 15.20.6178.019; Sat, 11 Mar 2023 08:24:31 +0000',
        ], $message->received->all());
        $this->assertEquals('This mail will not contain a body', $message->thread_topic);
        $this->assertEquals('AdlT8uVmpHPvImbCRM6E9LODIvAcQA==', $message->thread_index);
        $this->assertEquals('omef0ahNgeoJuEB51C568ED2227A2DAABB5BB9@omef0ahNgeoJu.eurprd02.prod.outlook.com', $message->message_id);
        $this->assertEquals('da-DK, en-US', $message->accept_language);
        $this->assertEquals('en-US', $message->content_language);
        $this->assertEquals('Internal', $message->x_ms_exchange_organization_authAs);
        $this->assertEquals('04', $message->x_ms_exchange_organization_authMechanism);
        $this->assertEquals('omef0ahNgeoJu.eurprd02.prod.outlook.com', $message->x_ms_exchange_organization_authSource);
        $this->assertEquals('', $message->x_ms_Has_Attach);
        $this->assertEquals('aa546a02-2b7a-4fb1-7fd4-08db220a09f1', $message->x_ms_exchange_organization_Network_Message_Id);
        $this->assertEquals('-1', $message->x_ms_exchange_organization_SCL);
        $this->assertEquals('', $message->x_ms_TNEF_Correlator);
        $this->assertEquals('0', $message->x_ms_exchange_organization_RecordReviewCfmType);
        $this->assertEquals('Email', $message->x_ms_publictraffictype);
        $this->assertEquals('ucf:0;jmr:0;auth:0;dest:I;ENG:(910001)(944506478)(944626604)(920097)(425001)(930097);', $message->X_Microsoft_Antispam_Mailbox_Delivery->first());
        $this->assertEquals('0712b5fe22cf6e75fa220501c1a6715a61098983df9e69bad4000c07531c1295', hash('sha256', $message->X_Microsoft_Antispam_Message_Info));
        $this->assertEquals('multipart/alternative', $message->Content_Type->last());
        $this->assertEquals('1.0', $message->mime_version);

        $this->assertCount(0, $message->getAttachments());
    }
}
