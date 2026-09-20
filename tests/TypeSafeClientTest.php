<?php

/**
 * TypeSafe AI PHP SDK
 * Copyright 2026 Alexey Kopytko <alexey@kopytko.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Tests\TypeSafeAI;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use JMS\Serializer\Exception\LogicException;
use Psr\Log\AbstractLogger;
use Stringable;
use TypeSafeAI\SystemOneRequest;
use TypeSafeAI\TypeSafeClient;

use function file_get_contents;
use function putenv;

/**
 * @covers \TypeSafeAI\TypeSafeClient
 */
class TypeSafeClientTest extends TestCase
{
    private static function request(): SystemOneRequest
    {
        return SystemOneRequest::build('Help! My payouts have been failing for 3 days.')
            ->noul('is_urgent', 'Does this convey urgency?');
    }

    public function testCreateInstance(): void
    {
        /** @var Client $httpClient */
        $httpClient = $this->getPropertyValue(TypeSafeClient::createInstance('secret'), 'client');

        $this->assertTrue($httpClient->getConfig('http_errors'));
        $this->assertFalse($httpClient->getConfig('allow_redirects'));
        $this->assertSame(3, $httpClient->getConfig('connect_timeout'));
        $this->assertSame(10, $httpClient->getConfig('timeout'));
        $this->assertSame('https://api.typesafe.ai', (string) $httpClient->getConfig('base_uri'));
    }

    public function testCreateInstanceReadsEnvironment(): void
    {
        putenv('TYPESAFE_API_KEY=from-env');
        putenv('TYPESAFE_BASE_URL=https://env.example.com');

        try {
            /** @var Client $httpClient */
            $httpClient = $this->getPropertyValue(TypeSafeClient::createInstance(), 'client');

            $this->assertSame('https://env.example.com', (string) $httpClient->getConfig('base_uri'));
            $this->assertSame('Bearer from-env', $httpClient->getConfig('headers')['Authorization']);
        } finally {
            putenv('TYPESAFE_API_KEY');
            putenv('TYPESAFE_BASE_URL');
        }
    }

    public function testCreateInstanceWithoutApiKey(): void
    {
        putenv('TYPESAFE_API_KEY');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No API key given and TYPESAFE_API_KEY is not set.');

        TypeSafeClient::createInstance();
    }

    public function testCreateInstanceWithClientOptions(): void
    {
        $client = $this->clientWith([self::success()], [
            'base_uri' => 'https://sandbox.example.com',
            'timeout' => 42,
        ]);

        $client->systemOne(self::request());

        $this->assertSame('https://sandbox.example.com/v1/systemone', (string) $this->getLastRequest()->getUri());
        $this->assertSame(42, $this->getLastOptions()['timeout']);
    }

    public function testSystemOne(): void
    {
        $client = $this->clientWith([self::success()]);

        $response = $client->systemOne(self::request());

        $this->assertSame(0.92, $response->noul('is_urgent')->noul);

        $request = $this->getLastRequest();

        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://api.typesafe.ai/v1/systemone', (string) $request->getUri());
        $this->assertSame('Bearer secret', $request->getHeaderLine('Authorization'));
        $this->assertSame('bar', $request->getHeaderLine('X-Foo'));
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
        $this->assertSame(
            '{"state":"Help! My payouts have been failing for 3 days.","model":"jev-latest","questions":{"is_urgent":{"type":"noul","instructions":"Does this convey urgency?"}}}',
            (string) $request->getBody(),
        );
    }

    public static function provideRetriedResponses(): iterable
    {
        yield 'request timeout' => [new Response(408)];
        yield 'too many requests' => [new Response(429)];
        yield 'server error' => [new Response(500)];
        yield 'service unavailable' => [new Response(503)];
        yield 'overloaded' => [new Response(529)];
        yield 'timeout' => [new ConnectException('Timed out', new Request('POST', '/'))];
    }

    /**
     * @dataProvider provideRetriedResponses
     */
    public function testRetries(object $failure): void
    {
        $client = $this->clientWith([$failure, $failure, self::success()]);

        $response = $client->systemOne(self::request());

        $this->assertSame('jev-latest', $response->model);
        $this->assertCount(0, $this->mock);
    }

    public function testUnknownAnswerType(): void
    {
        $client = $this->clientWith([new Response(200, [], '{"model": "jev-latest", "answers": {"order": {"type": "rank"}}, "usage": {"input_tokens": 1, "output_tokens": 1}}')]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The type value "rank" does not exist in the discriminator map');

        $client->systemOne(self::request());
    }

    public static function provideErrors(): iterable
    {
        yield 'bad request' => [400];
        yield 'unauthorized' => [401];
        yield 'unprocessable' => [422];
    }

    /**
     * @dataProvider provideErrors
     */
    public function testErrorsAreNotRetried(int $status): void
    {
        $client = $this->clientWith([new Response($status), self::success()]);

        try {
            $client->systemOne(self::request());
            $this->fail('No exception thrown');
        } catch (ClientException $e) {
            $this->assertSame($status, $e->getResponse()->getStatusCode());
        }

        $this->assertCount(1, $this->mock);
    }

    private static function logger(): AbstractLogger
    {
        return new class extends AbstractLogger {
            public array $messages = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->messages[] = (string) $message;
            }
        };
    }

    public function testSetLogger(): void
    {
        $logger = self::logger();

        $client = $this->clientWith([self::success()]);

        $this->assertSame($client, $client->setLogger($logger, '{method} {res_body}'));

        $response = $client->systemOne(self::request());

        // The logger reads the body first; the client must still see all of it
        $this->assertSame(0.92, $response->noul('is_urgent')->noul);
        $this->assertSame(['POST ' . file_get_contents(__DIR__ . '/data/evaluation_noul.json')], $logger->messages);
    }

    public function testSetLoggerDefaultTemplateHasNoHeaders(): void
    {
        $logger = self::logger();

        $client = $this->clientWith([self::success()]);
        $client->setLogger($logger);
        $client->systemOne(self::request());

        $this->assertSame([
            ">>>>>>>>\nPOST https://api.typesafe.ai/v1/systemone HTTP/1.1\n\n"
            . $this->getLastRequest()->getBody()
            . "\n<<<<<<<<\nHTTP/1.1 200 OK\n\n"
            . file_get_contents(__DIR__ . '/data/evaluation_noul.json')
            . "\n--------\nNULL",
        ], $logger->messages);
        $this->assertStringNotContainsString('secret', $logger->messages[0]);
    }
}
