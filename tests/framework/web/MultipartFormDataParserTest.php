<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yiiunit\framework\web;

use yii\web\MultipartFormDataParser;
use yii\web\Request;
use yiiunit\TestCase;

class MultipartFormDataParserTest extends TestCase
{
    public function testParse()
    {
        $parser = new MultipartFormDataParser();

        $boundary = '---------------------------22472926011618';
        $contentType = 'multipart/form-data; boundary=' . $boundary;
        $rawBody = "--{$boundary}\nContent-Disposition: form-data; name=\"title\"\r\n\r\ntest-title";
        $rawBody .= "\r\n--{$boundary}\nContent-Disposition: form-data; name=\"Item[name]\"\r\n\r\ntest-name";
        $rawBody .= "\r\n--{$boundary}\nContent-Disposition: form-data; name=\"someFile\"; filename=\"some-file.txt\"\nContent-Type: text/plain\r\n\r\nsome file content";
        $rawBody .= "\r\n--{$boundary}\nContent-Disposition: form-data; name=\"Item[file]\"; filename=\"item-file.txt\"\nContent-Type: text/plain\r\n\r\nitem file content";
        $rawBody .= "\r\n--{$boundary}--";

        $request = new Request([
            'rawBody' => $rawBody,
            'headers' => [
                'content-type' => [$contentType]
            ]
        ]);

        $bodyParams = $parser->parse($request);

        $expectedBodyParams = [
            'title' => 'test-title',
            'Item' => [
                'name' => 'test-name',
            ],
        ];
        $this->assertEquals($expectedBodyParams, $bodyParams);

        $this->assertNotEmpty($_FILES['someFile']);
        $this->assertEquals(UPLOAD_ERR_OK, $_FILES['someFile']['error']);
        $this->assertEquals('some-file.txt', $_FILES['someFile']['name']);
        $this->assertEquals('text/plain', $_FILES['someFile']['type']);
        $this->assertStringEqualsFile($_FILES['someFile']['tmp_name'], 'some file content');

        $this->assertNotEmpty($_FILES['Item']);
        $this->assertNotEmpty($_FILES['Item']['name']['file']);
        $this->assertEquals(UPLOAD_ERR_OK, $_FILES['Item']['error']['file']);
        $this->assertEquals('item-file.txt', $_FILES['Item']['name']['file']);
        $this->assertEquals('text/plain', $_FILES['Item']['type']['file']);
        $this->assertStringEqualsFile($_FILES['Item']['tmp_name']['file'], 'item file content');
    }

    /**
     * @depends testParse
     */
    public function testNotEmptyPost()
    {
        $parser = new MultipartFormDataParser();

        $_POST = [
            'name' => 'value',
        ];

        $request = new Request([
            'rawBody' => 'should not matter',
            'headers' => [
                'content-type' => ['multipart/form-data; boundary=---12345']
            ]
        ]);
        $bodyParams = $parser->parse($request);
        $this->assertEquals($_POST, $bodyParams);
        $this->assertEquals([], $_FILES);
    }

    /**
     * @depends testParse
     */
    public function testNotEmptyFiles()
    {
        $parser = new MultipartFormDataParser();

        $_FILES = [
            'file' => [
                'name' => 'file.txt',
                'type' => 'text/plain',
            ],
        ];

        $boundary = '---------------------------22472926011618';
        $contentType = 'multipart/form-data; boundary=' . $boundary;
        $rawBody = "--{$boundary}\nContent-Disposition: form-data; name=\"title\"\r\ntest-title--{$boundary}--";

        $request = new Request([
            'rawBody' => $rawBody,
            'headers' => [
                'content-type' => [$contentType]
            ]
        ]);
        $bodyParams = $parser->parse($request);
        $this->assertEquals([], $bodyParams);
    }

    /**
     * @depends testParse
     */
    public function testUploadFileMaxCount()
    {
        $parser = new MultipartFormDataParser();
        $parser->setUploadFileMaxCount(2);

        $boundary = '---------------------------22472926011618';
        $contentType = 'multipart/form-data; boundary=' . $boundary;
        $rawBody = "--{$boundary}\nContent-Disposition: form-data; name=\"firstFile\"; filename=\"first-file.txt\"\nContent-Type: text/plain\r\n\r\nfirst file content";
        $rawBody .= "--{$boundary}\nContent-Disposition: form-data; name=\"secondFile\"; filename=\"second-file.txt\"\nContent-Type: text/plain\r\n\r\nsecond file content";
        $rawBody .= "--{$boundary}\nContent-Disposition: form-data; name=\"thirdFile\"; filename=\"third-file.txt\"\nContent-Type: text/plain\r\n\r\nthird file content";
        $rawBody .= "--{$boundary}--";

        $request = new Request([
            'rawBody' => $rawBody,
            'headers' => [
                'content-type' => [$contentType]
            ]
        ]);
        $parser->parse($request);
        $this->assertCount(2, $_FILES);
    }

    /**
     * @depends testParse
     */
    public function testUploadFileMaxSize()
    {
        $parser = new MultipartFormDataParser();
        $parser->setUploadFileMaxSize(20);

        $boundary = '---------------------------22472926011618';
        $contentType = 'multipart/form-data; boundary=' . $boundary;
        $rawBody = "--{$boundary}\nContent-Disposition: form-data; name=\"firstFile\"; filename=\"first-file.txt\"\nContent-Type: text/plain\r\n\r\nfirst file content";
        $rawBody .= "--{$boundary}\nContent-Disposition: form-data; name=\"secondFile\"; filename=\"second-file.txt\"\nContent-Type: text/plain\r\n\r\nsecond file content";
        $rawBody .= "--{$boundary}\nContent-Disposition: form-data; name=\"thirdFile\"; filename=\"third-file.txt\"\nContent-Type: text/plain\r\n\r\nthird file with too long file content";
        $rawBody .= "--{$boundary}--";

        $request = new Request([
            'rawBody' => $rawBody,
            'headers' => [
                'content-type' => [$contentType]
            ]
        ]);
        $parser->parse($request);
        $this->assertCount(3, $_FILES);
        $this->assertEquals(UPLOAD_ERR_INI_SIZE, $_FILES['thirdFile']['error']);
    }

    /**
     * @depends testNotEmptyPost
     * @depends testNotEmptyFiles
     */
    public function testForce()
    {
        $parser = new MultipartFormDataParser();
        $parser->force = true;

        $_POST = [
            'existingName' => 'value',
        ];
        $_FILES = [
            'existingFile' => [
                'name' => 'file.txt',
                'type' => 'text/plain',
            ],
        ];

        $boundary = '---------------------------22472926011618';
        $contentType = 'multipart/form-data; boundary=' . $boundary;
        $rawBody = "--{$boundary}\nContent-Disposition: form-data; name=\"title\"\r\n\r\ntest-title";
        $rawBody .= "\r\n--{$boundary}\nContent-Disposition: form-data; name=\"someFile\"; filename=\"some-file.txt\"\nContent-Type: text/plain\r\n\r\nsome file content";
        $rawBody .= "\r\n--{$boundary}--";

        $request = new Request([
            'rawBody' => $rawBody,
            'headers' => [
                'content-type' => [$contentType]
            ]
        ]);
        $bodyParams = $parser->parse($request);

        $expectedBodyParams = [
            'title' => 'test-title',
        ];
        $this->assertEquals($expectedBodyParams, $bodyParams);
        $this->assertNotEmpty($_FILES['someFile']);
        $this->assertFalse(isset($_FILES['existingFile']));
    }
}
