<?php

namespace MailerLite\Tests\Common;

use Http\Mock\Client;
use MailerLite\Common\HttpLayer;
use MailerLite\Exceptions\MailerLiteException;
use MailerLite\Tests\TestCase;

class HttpLayerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new Client();
    }

    public function test_it_throws_when_api_key_is_missing()
    {
        $this->expectException(MailerLiteException::class);
        $this->expectExceptionMessage('Please set "api_key" in SDK options.');

        new HttpLayer([], $this->client);
    }

    public function test_it_throws_when_api_key_is_empty()
    {
        $this->expectException(MailerLiteException::class);

        new HttpLayer(['api_key' => ''], $this->client);
    }

    public function test_it_throws_when_api_key_is_null()
    {
        $this->expectException(MailerLiteException::class);

        new HttpLayer(['api_key' => null], $this->client);
    }

    public function test_it_sends_the_api_key_as_a_bearer_token()
    {
        $httpLayer = new HttpLayer(self::OPTIONS, $this->client);

        $this->client->addResponse(
            new \Nyholm\Psr7\Response(200, ['Content-Type' => 'application/json'], '{}')
        );

        $httpLayer->get('https://connect.mailerlite.com/api/subscribers');

        self::assertEquals('Bearer api-key', $this->client->getLastRequest()->getHeaderLine('Authorization'));
    }
}
