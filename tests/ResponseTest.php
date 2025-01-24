<?php

namespace Tests;

use Webklex\PHPIMAP\Connection\Response;
use Webklex\PHPIMAP\Exceptions\ResponseException;

class ResponseTest extends TestCase
{
    public function test_line_is_successful()
    {
        // Existing successful case
        $this->assertTrue(Response::isLineSuccessful(0, 'TAG0 OK Command completed'));

        // Existing failure cases
        $this->assertFalse(Response::isLineSuccessful(0, 'TAG0 BAD Syntax error in command'));
        $this->assertFalse(Response::isLineSuccessful(0, 'TAG0 NO Command is not valid in this state'));

        // Additional successful cases
        $this->assertTrue(Response::isLineSuccessful(1, 'TAG1 OK Another successful command'));
        $this->assertTrue(Response::isLineSuccessful(123, 'TAG123 OK Yet another success'));

        // Additional failure cases
        $this->assertFalse(Response::isLineSuccessful(2, 'TAG2 BAD Invalid credentials'));
        $this->assertFalse(Response::isLineSuccessful(3, 'TAG3 NO Operation not permitted'));

        // Edge cases
        $this->assertFalse(Response::isLineSuccessful(4, 'TAG4BAD Missing space after tag and status'));
        $this->assertTrue(Response::isLineSuccessful(5, 'TAG5OK No space but not matching BAD/NO'));
        $this->assertTrue(Response::isLineSuccessful(6, 'TAG6 BYE Server shutting down'));
        $this->assertFalse(Response::isLineSuccessful(7, 'TAG7 BAD'));
        $this->assertFalse(Response::isLineSuccessful(8, 'TAG8 NO'));

        // Case sensitivity
        $this->assertTrue(Response::isLineSuccessful(9, 'TAG9 ok lowercase status'));
        $this->assertTrue(Response::isLineSuccessful(10, 'TAG10 Ok Mixed case status'));

        // Malformed tags
        $this->assertTrue(Response::isLineSuccessful(11, 'T@G11 OK Malformed tag'));
        $this->assertTrue(Response::isLineSuccessful(12, 'TAG-12 OK Negative sequence'));

        // Empty and null lines
        $this->assertTrue(Response::isLineSuccessful(13, ''));
        $this->assertTrue(Response::isLineSuccessful(14, '   '));
    }

    public function test_constructor()
    {
        $response = new Response(100, true);
        $this->assertEquals(100, $response->sequence());
        $this->assertFalse($response->canBeEmpty());

        $this->assertIsInt((new Response)->sequence());
    }

    public function test_make_factory_method()
    {
        $commands = ['COMMAND1', 'COMMAND2'];
        $responses = ['RESPONSE1', 'RESPONSE2'];
        $response = Response::make(200, $commands, $responses, true);

        $this->assertEquals(200, $response->sequence());
        $this->assertTrue($response->boolean()); // Since 'RESPONSE1', 'RESPONSE2' are non-empty
        $this->assertEquals($commands, $response->getCommands());
        $this->assertEquals($responses, $response->getResponse());
        $this->assertTrue($response->successful());
    }

    public function test_empty_factory_method()
    {
        $response = Response::empty(true);
        $this->assertGreaterThan(0, $response->sequence());
        $this->assertFalse($response->boolean());
    }

    public function test_add_and_get_responses()
    {
        $response = new Response(1);
        $subResponse1 = new Response(2);
        $subResponse2 = new Response(3);

        $response->addResponse($subResponse1);
        $response->addResponse($subResponse2);

        $this->assertCount(2, $response->getResponses());
        $this->assertSame($subResponse1, $response->getResponses()[0]);
        $this->assertSame($subResponse2, $response->getResponses()[1]);
    }

    public function test_commands()
    {
        $response = new Response(10);

        // Test addCommand
        $response->addCommand('SELECT INBOX');
        $response->addCommand('FETCH 1');

        $this->assertCount(2, $response->getCommands());
        $this->assertEquals(['SELECT INBOX', 'FETCH 1'], $response->getCommands());

        // Test setCommands
        $newCommands = ['LOGOUT'];
        $response->setCommands($newCommands);
        $this->assertEquals($newCommands, $response->getCommands());
    }

    public function test_errors()
    {
        $response = new Response(20);

        // Initially, no errors
        $this->assertEmpty($response->getErrors());

        // Add errors
        $response->addError('Error 1');
        $response->addError('Error 2');

        $this->assertCount(2, $response->getErrors());
        $this->assertEquals(['Error 1', 'Error 2'], $response->getErrors());

        // Test setErrors
        $newErrors = ['Error A'];
        $response->setErrors($newErrors);
        $this->assertEquals($newErrors, $response->getErrors());
    }

    public function test_push_and_get_response()
    {
        $response = new Response(30);

        // Push responses
        $response->push('RESPONSE1');
        $response->push('RESPONSE2');

        $this->assertCount(2, $response->getResponse());
        $this->assertEquals(['RESPONSE1', 'RESPONSE2'], $response->getResponse());

        // Test setResponse
        $newResponse = ['RESPONSE3'];
        $response->setResponse($newResponse);
        $this->assertEquals($newResponse, $response->getResponse());
    }

    public function test_set_and_get_result()
    {
        $response = new Response(40);

        $this->assertEmpty($response->data());

        // Set result
        $result = ['DATA1', 'DATA2'];
        $response->setResult($result);
        $this->assertEquals($result, $response->data());

        // Overwrite result
        $newResult = 'Single String Result';
        $response->setResult($newResult);
        $this->assertEquals($newResult, $response->data());
    }

    public function test_data_methods()
    {
        $response = new Response(50);

        // Test with array result
        $arrayResult = ['element1', 'element2'];
        $response->setResult($arrayResult);

        $this->assertEquals($arrayResult, $response->data());
        $this->assertEquals($arrayResult, $response->array());
        $this->assertEquals('element1 element2', $response->string());
        $this->assertEquals(0, $response->integer()); // (int) ['element1', 'element2'] is 1, but in the original class, it checks if array has [0], which is 'element1', (int)'element1' is 0
        $this->assertTrue($response->boolean());

        // Test with string result
        $stringResult = 'Some string data';
        $response->setResult($stringResult);

        $this->assertEquals($stringResult, $response->data());
        $this->assertEquals(['Some string data'], $response->array());
        $this->assertEquals('Some string data', $response->string());
        $this->assertEquals(0, $response->integer()); // (int)'Some string data' is 0
        $this->assertTrue($response->boolean());

        // Test with integer result
        $integerResult = 123;
        $response->setResult($integerResult);

        $this->assertEquals($integerResult, $response->data());
        $this->assertEquals([123], $response->array());
        $this->assertEquals('123', $response->string());
        $this->assertEquals(123, $response->integer());
        $this->assertTrue($response->boolean());

        // Test with null result
        $response->setResult(null);

        $this->assertEmpty($response->data());
        $this->assertEquals([], $response->array());
        $this->assertEquals('', $response->string());
        $this->assertEquals(0, $response->integer());
        $this->assertFalse($response->boolean());
    }

    public function test_validation()
    {
        $response = new Response(60);
        $response->addError('An error occurred');

        $this->expectException(ResponseException::class);

        $response->validate();
    }

    public function test_get_validated_data()
    {
        $response = new Response(70);
        $response->setResult(['Valid data']);

        // Should return data without exception
        $this->assertEquals(['Valid data'], $response->getValidatedData());

        // Add an error and expect exception
        $response->addError('Another error');

        $this->expectException(ResponseException::class);

        $response->getValidatedData();
    }

    public function test_successful_and_failed()
    {
        // Successful response
        $responseSuccess = new Response(80);
        $responseSuccess->push('TAG80 OK Operation completed');
        $this->assertTrue($responseSuccess->successful());
        $this->assertFalse($responseSuccess->failed());

        // Failed response due to BAD status
        $responseBad = new Response(81);
        $responseBad->push('TAG81 BAD Syntax error');
        $this->assertFalse($responseBad->successful());
        $this->assertTrue($responseBad->failed());

        // Failed response due to NO status
        $responseNo = new Response(82);
        $responseNo->push('TAG82 NO Operation not allowed');
        $this->assertFalse($responseNo->successful());
        $this->assertTrue($responseNo->failed());

        // Mixed responses
        $responseMixed = new Response(83);
        $responseMixed->push('TAG83 OK Operation completed');
        $responseMixed->push('TAG83 BAD Partial failure');
        $this->assertFalse($responseMixed->successful());
        $this->assertTrue($responseMixed->failed());

        // Responses with sub-responses
        $subResponse = new Response(84);
        $subResponse->push('TAG84 OK Sub-operation completed');

        $responseWithSub = new Response(85);
        $responseWithSub->addResponse($subResponse);
        $responseWithSub->push('TAG85 OK Main operation completed');

        $this->assertTrue($responseWithSub->successful());

        // Add an error in sub-response
        $subResponse->push('TAG84 BAD Sub-operation failed');
        $this->assertFalse($responseWithSub->successful());
    }

    public function test_complex_responses_and_errors()
    {
        $response = new Response(90);
        $response->addCommand('COMMAND1');
        $response->push('TAG90 OK Response1');

        $subResponse1 = new Response(91);
        $subResponse1->addCommand('COMMAND2');
        $subResponse1->push('TAG91 OK Response2');

        $subResponse2 = new Response(92);
        $subResponse2->addCommand('COMMAND3');
        $subResponse2->push('TAG92 BAD Response3');

        $response->addResponse($subResponse1);
        $response->addResponse($subResponse2);

        // The main response should be failed due to subResponse2
        $this->assertFalse($response->successful());

        // Check errors aggregation
        $this->assertEmpty($response->getErrors()); // No explicit errors added
        $this->assertEmpty($response->getErrors()); // Errors are determined by responses, not stored
    }

    public function test_sequence()
    {
        $sequence = 1000;
        $response = new Response($sequence);
        $this->assertEquals($sequence, $response->sequence());

        $defaultResponse = new Response;
        $this->assertIsInt($defaultResponse->sequence());
    }

    public function test_can_be_empty()
    {
        $response = new Response(110);

        // Default value
        $this->assertFalse($response->canBeEmpty());

        // Set canBeEmpty to true
        $response->setCanBeEmpty(true);
        $this->assertTrue($response->canBeEmpty());

        // Set back to false
        $response->setCanBeEmpty(false);
        $this->assertFalse($response->canBeEmpty());
    }
}
