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

declare(strict_types=1);

namespace Tests\TypeSafeAI;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use Tests\TypeSafeAI\Doubles\RankQuestion;
use Tests\TypeSafeAI\Doubles\ExampleState;
use Tests\TypeSafeAI\Doubles\TicketDecision;
use TypeSafeAI\OpenAICompatClient;
use TypeSafeAI\SystemOneRequest;
use UnexpectedValueException;

use function json_decode;
use function json_encode;
use function putenv;

/**
 * @covers \TypeSafeAI\OpenAICompatClient
 * @covers \TypeSafeAI\EvaluatesAttributes
 * @covers \TypeSafeAI\RequestContext
 */
class OpenAICompatClientTest extends TestCase
{
    private function client(array $responses, array $requestOptions = []): OpenAICompatClient
    {
        $this->mock = new MockHandler($responses);

        $stack = HandlerStack::create($this->mock);
        $stack->push(Middleware::history($this->requests));

        return OpenAICompatClient::createInstance('http://127.0.0.1:8080/v1', 'secret', $requestOptions, ['handler' => $stack]);
    }

    private static function completion(array $probabilities, int $input = 100, int $output = 10): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'model' => 'LocalLLM',
            'choices' => [[
                'index' => 0,
                'finish_reason' => 'stop',
                'message' => ['role' => 'assistant', 'content' => json_encode(['probabilities' => $probabilities])],
            ]],
            'usage' => ['prompt_tokens' => $input, 'completion_tokens' => $output, 'total_tokens' => $input + $output],
        ]));
    }

    private function requestBody(int $index): array
    {
        return json_decode((string) $this->requests[$index]['request']->getBody(), true);
    }

    public static function provideEnvironments(): iterable
    {
        yield 'defaults' => [[], [], 'https://api.openai.com/v1/', null];

        yield 'environment' => [
            ['OPENAI_BASE_URL=http://127.0.0.1:4000/v1', 'OPENAI_API_KEY=from-env'],
            [],
            'http://127.0.0.1:4000/v1/',
            'Bearer from-env',
        ];

        yield 'arguments over environment' => [
            ['OPENAI_BASE_URL=http://127.0.0.1:4000/v1', 'OPENAI_API_KEY=from-env'],
            ['http://127.0.0.1:8080/v1/', 'secret'],
            'http://127.0.0.1:8080/v1/',
            'Bearer secret',
        ];

        yield 'endpoint from environment, key from argument' => [
            ['OPENAI_BASE_URL=http://127.0.0.1:4000/v1'],
            [null, 'secret'],
            'http://127.0.0.1:4000/v1/',
            'Bearer secret',
        ];
    }

    /**
     * @dataProvider provideEnvironments
     * @param list<string> $environment
     * @param list<?string> $arguments
     */
    public function testCreateInstance(array $environment, array $arguments, string $baseUri, ?string $authorization): void
    {
        putenv('OPENAI_BASE_URL');
        putenv('OPENAI_API_KEY');

        foreach ($environment as $setting) {
            putenv($setting);
        }

        try {
            /** @var Client $httpClient */
            $httpClient = $this->getPropertyValue(OpenAICompatClient::createInstance(...$arguments), 'client');
        } finally {
            putenv('OPENAI_BASE_URL');
            putenv('OPENAI_API_KEY');
        }

        $this->assertSame($baseUri, (string) $httpClient->getConfig('base_uri'));
        $this->assertSame($authorization, $httpClient->getConfig('headers')['Authorization'] ?? null);
        $this->assertSame(120, $httpClient->getConfig('timeout'));
        $this->assertTrue($httpClient->getConfig('http_errors'));
        $this->assertFalse($httpClient->getConfig('allow_redirects'));
    }

    public function testSystemOne(): void
    {
        $client = $this->client([
            self::completion(['no' => 0.1, 'yes' => 0.9], 150, 20),
            self::completion(['billing' => 0.2, 'technical' => 0.8, 'sales' => 0.0], 160, 30),
            self::completion(['0' => 0.1, '1' => 0.2, '2' => 0.7], 170, 40),
        ]);

        $response = $client->systemOne(
            SystemOneRequest::build(['message' => 'Help! My payouts have been failing for 3 days.'], 'qwen')
                ->noul('is_urgent', 'Does this convey urgency?', true: 'Explicitly time-sensitive')
                ->choice('department', 'Which team should handle this?', [
                    'billing' => 'Payments, invoicing, refunds',
                    'technical' => 'Bugs, outages, integrations',
                    'sales' => null,
                ])
                ->score('frustration', 'How frustrated is the customer?', ['Calm', 'Frustrated', 'Very angry']),
        );

        $this->assertSame('LocalLLM', $response->model);
        $this->assertSame(480, $response->usage->input_tokens);
        $this->assertSame(90, $response->usage->output_tokens);

        $this->assertSame(0.9, $response->noul('is_urgent')->noul);

        $department = $response->choice('department');
        $this->assertSame('technical', $department->choice);
        $this->assertSame(['billing' => 0.2, 'technical' => 0.8, 'sales' => 0.0], $department->probabilities);
        $this->assertSame(0.8, $department->confidence);

        $frustration = $response->score('frustration');
        $this->assertEqualsWithDelta(1.6, $frustration->score, 1e-9);
        $this->assertSame(['Calm', 'Frustrated', 'Very angry'], $frustration->legend);
        $this->assertSame([0.1, 0.2, 0.7], $frustration->probabilities);
        $this->assertSame(0.7, $frustration->confidence);

        $this->assertCount(0, $this->mock);

        $request = $this->getLastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('http://127.0.0.1:8080/v1/chat/completions', (string) $request->getUri());
        $this->assertSame('Bearer secret', $request->getHeaderLine('Authorization'));
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));

        $this->assertSame([
            'model' => 'qwen',
            'messages' => [
                ['role' => 'system', 'content' => OpenAICompatClient::SYSTEM_PROMPT],
                ['role' => 'user', 'content' => "State:\n{\"message\":\"Help! My payouts have been failing for 3 days.\"}\n\nDoes this convey urgency?\n\nOptions:\n- no\n- yes: Explicitly time-sensitive\n\nOutput probabilities over exactly these keys: [\"no\", \"yes\"]."],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'distribution',
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'probabilities' => [
                                'type' => 'object',
                                'properties' => ['no' => ['type' => 'number'], 'yes' => ['type' => 'number']],
                                'required' => ['no', 'yes'],
                                'additionalProperties' => false,
                            ],
                        ],
                        'required' => ['probabilities'],
                        'additionalProperties' => false,
                    ],
                    'strict' => true,
                ],
            ],
        ], $this->requestBody(0));

        // A choice option without a description is still an option
        $this->assertSame(['billing', 'technical', 'sales'], $this->requestBody(1)['response_format']['json_schema']['schema']['properties']['probabilities']['required']);

        // Level indices must be keys of an object, not a list
        $this->assertStringContainsString(
            '"properties":{"0":{"type":"number"},"1":{"type":"number"},"2":{"type":"number"}}',
            (string) $this->getLastRequest()->getBody(),
        );
    }

    public function testStringState(): void
    {
        $client = $this->client([self::completion(['no' => 0.5, 'yes' => 0.5])]);

        $client->systemOne(SystemOneRequest::build('Plain text')->noul('is_urgent', 'Urgent?'));

        $this->assertSame(
            "State:\nPlain text\n\nUrgent?\n\nOptions:\n- no\n- yes\n\nOutput probabilities over exactly these keys: [\"no\", \"yes\"].",
            $this->requestBody(0)['messages'][1]['content'],
        );
    }

    public function testNoQuestions(): void
    {
        $response = $this->client([])->systemOne(SystemOneRequest::build('Plain text', 'qwen'));

        $this->assertSame('qwen', $response->model);
        $this->assertSame([], $response->answers);
        $this->assertSame(0, $response->usage->input_tokens);
        $this->assertSame(0, $response->usage->output_tokens);
    }

    public function testRequestOptions(): void
    {
        $client = $this->client([self::completion(['no' => 0.5, 'yes' => 0.5])], [
            'max_tokens' => 16384,
            'model' => null,
            'chat_template_kwargs' => ['enable_thinking' => true],
        ]);

        $client->systemOne(SystemOneRequest::build('Plain text')->noul('is_urgent', 'Urgent?'));

        $body = $this->requestBody(0);

        $this->assertSame(16384, $body['max_tokens']);
        $this->assertArrayNotHasKey('model', $body);
        $this->assertSame(['enable_thinking' => true], $body['chat_template_kwargs']);
        $this->assertSame('json_schema', $body['response_format']['type']);
    }

    public function testEvaluate(): void
    {
        $client = $this->client([
            self::completion(['no' => 0.1, 'yes' => 0.9]),
            self::completion(['billing' => 0.2, 'technical' => 0.8, 'sales' => 0.0]),
            self::completion(['0' => 0.1, '1' => 0.2, '2' => 0.7]),
        ]);

        $decision = $client->evaluate('Help!', TicketDecision::class, 'qwen');

        $this->assertInstanceOf(TicketDecision::class, $decision);
        $this->assertSame(0.9, $decision->is_urgent->noul);
        $this->assertSame('technical', $decision->department->choice);
        $this->assertSame(0.7, $decision->frustration->confidence);
        $this->assertSame('qwen', $this->requestBody(0)['model']);
    }

    public function testInvalidDistribution(): void
    {
        $client = $this->client([self::completion(['no' => 0.5, 'yes' => 0.6])]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Probabilities sum to 1.1');

        $client->systemOne(SystemOneRequest::build('Plain text')->noul('is_urgent', 'Urgent?'));
    }

    public static function provideUndecodableContent(): iterable
    {
        yield 'prose' => ['The answer is yes.'];
        yield 'scalar' => ['0.9'];
    }

    /**
     * @dataProvider provideUndecodableContent
     */
    public function testUndecodableContent(string $content): void
    {
        $client = $this->client([new Response(200, [], json_encode([
            'model' => 'LocalLLM',
            'choices' => [['message' => ['content' => $content]]],
            'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
        ]))]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage("Expected a JSON object, got $content");

        $client->systemOne(SystemOneRequest::build('Plain text')->noul('is_urgent', 'Urgent?'));
    }

    public function testObjectState(): void
    {
        $client = $this->client([self::completion(['no' => 0.5, 'yes' => 0.5])]);

        $client->systemOne(SystemOneRequest::build(new ExampleState())->noul('is_urgent', 'Urgent?'));

        // Private properties and nulls, as TypeSafeClient sends them
        $this->assertStringStartsWith(
            "State:\n{\"id\":42,\"assignee\":null,\"secret\":\"private properties are sent too\"}\n\n",
            $this->requestBody(0)['messages'][1]['content'],
        );
    }

    public function testUnsupportedQuestion(): void
    {
        $client = $this->client([]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Question type Tests\\TypeSafeAI\\Doubles\\RankQuestion is not supported');

        $client->systemOne(SystemOneRequest::build('Plain text')->ask('order', new RankQuestion()));
    }

    public function testErrorsAreNotRetried(): void
    {
        $client = $this->client([new Response(503), self::completion(['no' => 0.5, 'yes' => 0.5])]);

        try {
            $client->systemOne(SystemOneRequest::build('Plain text')->noul('is_urgent', 'Urgent?'));
            $this->fail('No exception thrown');
        } catch (ServerException $e) {
            $this->assertSame(503, $e->getResponse()->getStatusCode());
        }

        $this->assertCount(1, $this->mock);
    }
}
