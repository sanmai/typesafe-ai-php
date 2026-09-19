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

namespace TypeSafeAI;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use GuzzleRetry\GuzzleRetryMiddleware;
use JMS\Serializer\Exception\LogicException;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use JSONSerializer\Serializer;
use Psr\Log\LoggerInterface;

use function array_merge;

/**
 * TypeSafe AI API Client.
 */
class TypeSafeClient
{
    public const BASE_URI = 'https://api.typesafe.ai';

    private const EVALUATE = '/v1/systemone';

    private const HTTP_TOO_MANY_REQUESTS = 429;

    private const HTTP_OVERLOADED = 529;

    /**
     * Like MessageFormatter::DEBUG, but without headers
     */
    public const LOG_TEMPLATE = ">>>>>>>>\n{method} {uri} HTTP/{version}\n\n{req_body}\n<<<<<<<<\nHTTP/{version} {code} {phrase}\n\n{res_body}\n--------\n{error}";

    /**
     * Build a new client instance.
     *
     * @param string $apiKey TypeSafe API key
     * @param array<string, string> $extraHeaders Additional HTTP headers to send with every request
     * @param array<string, mixed> $retryOptions Options passed to GuzzleRetryMiddleware (retry_on_status, etc.)
     * @param array<string, mixed> $clientOptions Extra Guzzle client options (base_uri, timeout, etc.) merged after defaults
     */
    public static function createInstance(
        string $apiKey,
        array $extraHeaders = [],
        array $retryOptions = [],
        array $clientOptions = [],
    ): self {
        $stack = HandlerStack::create();

        $stack->push(GuzzleRetryMiddleware::factory(array_merge([
            'retry_on_timeout' => true,
            'retry_on_status' => [
                self::HTTP_TOO_MANY_REQUESTS,
                self::HTTP_OVERLOADED,
            ],
        ], $retryOptions)), 'retry_on_status');

        $httpClient = new Client(array_merge([
            'base_uri' => self::BASE_URI,
            'connect_timeout' => 3,
            'timeout' => 120,
            'http_errors' => true,
            'allow_redirects' => false,
            'headers' => array_merge([
                'Authorization' => "Bearer $apiKey",
            ], $extraHeaders),
            'handler' => $stack,
        ], $clientOptions));

        return new self(
            $httpClient,
            $stack,
            Serializer::withJSONOptions(),
        );
    }

    public function __construct(
        private readonly Client $client,
        private readonly HandlerStack $stack,
        private readonly SerializerInterface $serializer,
    ) {}

    public function setLogger(LoggerInterface $logger, string $template = self::LOG_TEMPLATE): self
    {
        $this->stack->push(Middleware::log(
            $logger,
            new MessageFormatter($template),
        ));

        return $this;
    }

    /**
     * Answers each question about the state.
     *
     * @throws GuzzleException On 401 (bad API key), 422 (invalid request), or when retries run out
     * @throws LogicException When an answer has a type that the SDK does not know
     */
    public function evaluate(EvaluationRequest $request): EvaluationResponse
    {
        $response = $this->client->post(self::EVALUATE, [
            'body' => $this->serializer->serialize($request, 'json', self::serializationContext()),
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        /** @var EvaluationResponse $result */
        $result = $this->serializer->deserialize(
            (string) $response->getBody(),
            EvaluationResponse::class,
            'json',
        );

        return $result;
    }

    /**
     * Sends null values, such as a choice option without a description.
     */
    private static function serializationContext(): SerializationContext
    {
        return SerializationContext::create()->setSerializeNull(true);
    }
}
