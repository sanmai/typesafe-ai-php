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
use InvalidArgumentException;
use JMS\Serializer\SerializerInterface;
use JSONSerializer\Contracts\JsonDeserializer;
use JSONSerializer\Serializer;
use TypeSafeAI\OpenAICompat\ChoiceDecision;
use TypeSafeAI\OpenAICompat\Decision;
use TypeSafeAI\OpenAICompat\Distribution;
use TypeSafeAI\OpenAICompat\NoulDecision;
use TypeSafeAI\OpenAICompat\ScoreDecision;
use TypeSafeAI\OpenAICompat\Text;
use UnexpectedValueException;

use function array_fill_keys;
use function array_filter;
use function array_merge;
use function json_decode;
use function json_encode;
use function rtrim;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * Evaluates questions with a chat model through an OpenAI-compatible API, such as llama.cpp.
 *
 * The model writes a probability distribution over the options of each question, one request for each question.
 *
 * @phpstan-type Completion array{model: string, choices: list<array{message: array{content: string}}>, usage: array{prompt_tokens: int, completion_tokens: int}}
 * @phpstan-type WireQuestion array{type: string, instructions?: mixed, criteria?: array<mixed>}
 */
class OpenAICompatClient implements SystemOneClient
{
    use EvaluatesAttributes;

    public const SYSTEM_PROMPT = "You are a calibration engine. You never answer in prose. You output only a JSON object with the key 'probabilities' mapping every given option to a probability, all options included, values in [0,1], summing to 1.";

    private const CHAT_COMPLETIONS = 'chat/completions';

    private const TIMEOUT = 120;

    private const MAX_TOKENS = 4096;

    /**
     * Build a new client instance.
     *
     * @param string $endpoint The API root with the version, such as http://127.0.0.1:8080/v1
     * @param array<string, mixed> $requestOptions Fields to add to each request body; a null value removes the field
     * @param array<string, mixed> $clientOptions Extra Guzzle client options (timeout, headers, etc.) merged after defaults
     */
    public static function createInstance(
        string $endpoint,
        ?string $apiKey = null,
        array $requestOptions = [],
        array $clientOptions = [],
    ): self {
        $httpClient = new Client(array_merge([
            'base_uri' => rtrim($endpoint, '/') . '/',
            'timeout' => self::TIMEOUT,
            'http_errors' => true,
            'allow_redirects' => false,
            'headers' => null === $apiKey ? [] : ['Authorization' => "Bearer $apiKey"],
        ], $clientOptions));

        return new self($httpClient, Serializer::withJSONOptions(), $requestOptions);
    }

    /**
     * @param array<string, mixed> $requestOptions
     */
    public function __construct(
        private readonly Client $client,
        private readonly SerializerInterface&JsonDeserializer $serializer,
        private readonly array $requestOptions = [],
    ) {}

    /**
     * Answers each question about the state, one request for each question.
     *
     * @throws GuzzleException On an HTTP error
     * @throws UnexpectedValueException When the model returns an invalid distribution
     * @throws InvalidArgumentException When a question type is not supported
     */
    public function systemOne(SystemOneRequest $request): SystemOneResult
    {
        /** @var array{state: mixed, questions: array<array-key, WireQuestion>} $wire */
        $wire = json_decode(
            $this->serializer->serialize($request, 'json', RequestContext::create()),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $result = [
            'model' => $request->model,
            'answers' => [],
            'usage' => ['input_tokens' => 0, 'output_tokens' => 0],
        ];

        foreach ($wire['questions'] as $id => $question) {
            $decision = self::decision($question);
            $completion = $this->complete($request->model, Text::of($wire['state']), $decision);

            $result['answers'][$id] = $decision->answer(
                Distribution::parse($completion['choices'][0]['message']['content'], $decision->labels()),
            );
            $result['model'] = $completion['model'];
            $result['usage']['input_tokens'] += $completion['usage']['prompt_tokens'];
            $result['usage']['output_tokens'] += $completion['usage']['completion_tokens'];
        }

        return $this->serializer->deserializeJson(
            json_encode($result, JSON_THROW_ON_ERROR),
            SystemOneResult::class,
        );
    }

    /**
     * @param WireQuestion $question
     */
    private static function decision(array $question): Decision
    {
        $instructions = $question['instructions'] ?? null;
        $criteria = $question['criteria'] ?? [];

        return match ($question['type']) {
            'noul' => new NoulDecision($instructions, $criteria),
            'choice' => new ChoiceDecision($instructions, $criteria),
            'score' => new ScoreDecision($instructions, $criteria),
            default => throw new InvalidArgumentException(sprintf('Question type "%s" is not supported', $question['type'])),
        };
    }

    /**
     * @return Completion
     */
    private function complete(string $model, string $state, Decision $decision): array
    {
        $response = $this->client->post(self::CHAT_COMPLETIONS, [
            'json' => $this->body($model, "State:\n$state\n\n" . $decision->prompt(), $decision->labels()),
        ]);

        /** @var Completion */
        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @param list<string> $labels
     * @return array<string, mixed>
     */
    private function body(string $model, string $message, array $labels): array
    {
        return array_filter(array_merge([
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                ['role' => 'user', 'content' => $message],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => ['name' => 'distribution', 'schema' => self::schema($labels), 'strict' => true],
            ],
            'temperature' => 0,
            'max_tokens' => self::MAX_TOKENS,
        ], $this->requestOptions), static fn($value) => null !== $value);
    }

    /**
     * @param list<string> $labels
     * @return array<string, mixed>
     */
    private static function schema(array $labels): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'probabilities' => [
                    'type' => 'object',
                    // An object also for the level indices of a score
                    'properties' => (object) array_fill_keys($labels, ['type' => 'number']),
                    'required' => $labels,
                    'additionalProperties' => false,
                ],
            ],
            'required' => ['probabilities'],
            'additionalProperties' => false,
        ];
    }
}
