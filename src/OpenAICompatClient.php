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

use function array_filter;
use function array_map;
use function array_merge;
use function get_debug_type;
use function getenv;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use JMS\Serializer\Exception\RuntimeException;
use JMS\Serializer\SerializerInterface;
use JSONSerializer\Contracts\JsonDeserializer;
use JSONSerializer\Serializer;

use function rtrim;
use function sprintf;

use TypeSafeAI\OpenAICompat\Decision\ChoiceDecision;
use TypeSafeAI\OpenAICompat\Decision\Decision;
use TypeSafeAI\OpenAICompat\Decision\NoulDecision;
use TypeSafeAI\OpenAICompat\Decision\ScoreDecision;
use TypeSafeAI\OpenAICompat\DTO\Completion;
use TypeSafeAI\OpenAICompat\DTO\Distributions;
use TypeSafeAI\OpenAICompat\Schema;
use TypeSafeAI\OpenAICompat\SystemPrompt;
use TypeSafeAI\OpenAICompat\ValueFormatter;
use TypeSafeAI\Question\Choice;
use TypeSafeAI\Question\Noul;
use TypeSafeAI\Question\Question;
use TypeSafeAI\Question\Score;
use UnexpectedValueException;

/**
 * Evaluates questions with a chat model through an OpenAI-compatible API, such as llama.cpp.
 *
 * The model writes a probability distribution over the options of each question, all questions in one request.
 *
 * @final
 */
class OpenAICompatClient implements SystemOneClient
{
    use SystemOneEvaluator;

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

        $serializer = Serializer::withJSONOptions();

        return new self(
            $httpClient,
            $serializer,
            new ValueFormatter($serializer),
            $requestOptions,
        );
    }

    /**
     * @param array<string, mixed> $requestOptions
     */
    public function __construct(
        private readonly Client $client,
        private readonly SerializerInterface&JsonDeserializer $serializer,
        private readonly ValueFormatter $formatter,
        private readonly array $requestOptions = [],
    ) {}

    /**
     * Answers all questions about the state with a single chat request.
     *
     * @throws GuzzleException On an HTTP error
     * @throws RuntimeException When the model writes content that is not JSON
     * @throws InvalidArgumentException When a question type is not supported
     * @throws UnexpectedValueException When the model writes no probabilities for a question
     */
    public function systemOne(SystemOneRequest $request): SystemOneResult
    {
        $decisions = array_map($this->decision(...), $request->questions);
        $completion = $this->complete($request, $decisions);

        return $this->result($completion, $decisions);
    }

    /**
     * @param array<string, Decision> $decisions
     */
    private function result(Completion $completion, array $decisions): SystemOneResult
    {
        $distributions = $this->serializer->deserializeJson($completion->choices[0]->message->content, Distributions::class);

        $result = new SystemOneResult();
        $result->model = $completion->model;
        $result->usage->input_tokens = $completion->usage->prompt_tokens;
        $result->usage->output_tokens = $completion->usage->completion_tokens;
        $result->answers = [];

        foreach ($decisions as $id => $decision) {
            $result->answers[$id] = $decision->answer($distributions->probabilities($id));
        }

        return $result;
    }

    private function decision(Question $question): Decision
    {
        return match (true) {
            $question instanceof Noul => new NoulDecision($question, $this->formatter),
            $question instanceof Choice => new ChoiceDecision($question, $this->formatter),
            $question instanceof Score => new ScoreDecision($question, $this->formatter),
            default => throw new InvalidArgumentException(sprintf('Question type %s is not supported', get_debug_type($question))),
        };
    }

    /**
     * @param array<array-key, Decision> $decisions
     */
    private function complete(SystemOneRequest $request, array $decisions): Completion
    {
        $response = $this->client->post(self::CHAT_COMPLETIONS, [
            'json' => $this->body($request->model, SystemPrompt::of($decisions), $this->formatter->format($request->state), Schema::of($decisions)),
        ]);

        return $this->serializer->deserializeJson((string) $response->getBody(), Completion::class);
    }

    /**
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private function body(string $model, string $system, string $user, array $schema): array
    {
        return array_filter(array_merge([
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => ['name' => 'distributions', 'schema' => $schema, 'strict' => true],
            ],
        ], $this->requestOptions), static fn($value) => null !== $value);
    }
}
