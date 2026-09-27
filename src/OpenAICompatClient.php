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
use JMS\Serializer\Exception\RuntimeException;
use JMS\Serializer\SerializerInterface;
use JSONSerializer\Contracts\JsonDeserializer;
use JSONSerializer\Serializer;
use TypeSafeAI\DTO\Usage;
use TypeSafeAI\OpenAICompat\ChoiceDecision;
use TypeSafeAI\OpenAICompat\Completion;
use TypeSafeAI\OpenAICompat\Decision;
use TypeSafeAI\OpenAICompat\Distribution;
use TypeSafeAI\OpenAICompat\NoulDecision;
use TypeSafeAI\OpenAICompat\ScoreDecision;
use TypeSafeAI\OpenAICompat\Text;
use TypeSafeAI\Question\Choice;
use TypeSafeAI\Question\Noul;
use TypeSafeAI\Question\Question;
use TypeSafeAI\Question\Score;
use UnexpectedValueException;

use function array_fill_keys;
use function array_filter;
use function array_merge;
use function get_debug_type;
use function getenv;
use function rtrim;
use function sprintf;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Evaluates questions with a chat model through an OpenAI-compatible API, such as llama.cpp.
 *
 * The model writes a probability distribution over the options of each question, one request for each question.

 */
class OpenAICompatClient implements SystemOneClient
{
    use EvaluatesAttributes;

    public const SYSTEM_PROMPT = "You are a calibration engine. You never answer in prose. You output only a JSON object with the key 'probabilities' mapping every given option to a probability, all options included, values in [0,1], summing to 1.";

    public const BASE_URI = 'https://api.openai.com/v1';

    public const BASE_URL_ENV = 'OPENAI_BASE_URL';

    public const API_KEY_ENV = 'OPENAI_API_KEY';

    private const CHAT_COMPLETIONS = 'chat/completions';

    private const TIMEOUT = 120;

    /**
     * Build a new client instance.
     *
     * @param string|null $endpoint The API root with the version, such as http://127.0.0.1:8080/v1; read from OPENAI_BASE_URL when null
     * @param string|null $apiKey Read from OPENAI_API_KEY when null; no key is sent when neither is set
     * @param array<string, mixed> $requestOptions Fields to add to each request body; a null value removes the field
     * @param array<string, mixed> $clientOptions Extra Guzzle client options (timeout, headers, etc.) merged after defaults
     */
    public static function createInstance(
        ?string $endpoint = null,
        ?string $apiKey = null,
        array $requestOptions = [],
        array $clientOptions = [],
    ): self {
        $endpoint ??= getenv(self::BASE_URL_ENV) ?: self::BASE_URI;
        $apiKey ??= getenv(self::API_KEY_ENV) ?: null;

        $httpClient = new Client(array_merge([
            'base_uri' => rtrim($endpoint, '/') . '/',
            'timeout' => self::TIMEOUT,
            'http_errors' => true,
            'allow_redirects' => false,
            'headers' => null === $apiKey ? [] : ['Authorization' => "Bearer $apiKey"],
        ], $clientOptions));

        return new self(
            $httpClient,
            Serializer::withJSONOptions(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $requestOptions,
        );
    }

    private readonly Text $text;

    /**
     * @param array<string, mixed> $requestOptions
     */
    public function __construct(
        private readonly Client $client,
        private readonly SerializerInterface&JsonDeserializer $serializer,
        private readonly array $requestOptions = [],
    ) {
        $this->text = new Text($serializer);
    }

    /**
     * Answers each question about the state, one request for each question.
     *
     * @throws GuzzleException On an HTTP error
     * @throws UnexpectedValueException When the model returns an invalid distribution
     * @throws InvalidArgumentException When a question type is not supported
     */
    public function systemOne(SystemOneRequest $request): SystemOneResult
    {
        $result = new SystemOneResult();
        $result->model = $request->model;
        $result->answers = [];
        $result->usage = new Usage();
        $result->usage->input_tokens = 0;
        $result->usage->output_tokens = 0;

        $state = $this->text->of($request->state);

        foreach ($request->questions as $id => $question) {
            $decision = $this->decision($question);
            $completion = $this->complete($request->model, $state, $decision);

            $result->answers[$id] = $decision->answer(
                Distribution::of($this->decode($completion->choices[0]->message->content), $decision->labels()),
            );
            $result->model = $completion->model;
            $result->usage->input_tokens += $completion->usage->prompt_tokens;
            $result->usage->output_tokens += $completion->usage->completion_tokens;
        }

        return $result;
    }

    private function decision(Question $question): Decision
    {
        return match (true) {
            $question instanceof Noul => new NoulDecision($question, $this->text),
            $question instanceof Choice => new ChoiceDecision($question, $this->text),
            $question instanceof Score => new ScoreDecision($question, $this->text),
            default => throw new InvalidArgumentException(sprintf('Question type %s is not supported', get_debug_type($question))),
        };
    }

    private function complete(string $model, string $state, Decision $decision): Completion
    {
        $response = $this->client->post(self::CHAT_COMPLETIONS, [
            'json' => $this->body($model, "State:\n$state\n\n" . $decision->prompt(), $decision->labels()),
        ]);

        return $this->serializer->deserializeJson((string) $response->getBody(), Completion::class);
    }

    /**
     * Decodes the content that the model writes, without changes to its values: Distribution validates them.
     *
     * @return array<mixed>
     * @throws UnexpectedValueException When the content is not a JSON object or array
     */
    private function decode(string $content): array
    {
        try {
            /** @var array<mixed> */
            return $this->serializer->deserialize($content, 'array', 'json');
        } catch (RuntimeException $e) {
            throw new UnexpectedValueException(sprintf('Expected a JSON object, got %s', $content), previous: $e);
        }
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
