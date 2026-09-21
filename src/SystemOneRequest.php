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

use JMS\Serializer\Annotation\Type;
use TypeSafeAI\Question\Choice;
use TypeSafeAI\Question\Noul;
use TypeSafeAI\Question\NoulCriteria;
use TypeSafeAI\Question\Question;
use TypeSafeAI\Question\Score;

/**
 * The state to evaluate, and the questions to answer about it.
 *
 * @phpstan-import-type EntryType from Question
 */
class SystemOneRequest
{
    public const MODEL_LATEST = 'jev-latest';

    /**
     * @param string|array<mixed>|object $state Text, or structured data such as a chat log.
     * @param array<string, Question> $questions Answers come back under the same ids.
     */
    public function __construct(
        public string|array|object $state,
        public string $model = self::MODEL_LATEST,
        #[Type('array<string, TypeSafeAI\Question\Question>')]
        public array $questions = [],
    ) {}

    /**
     * Same as the constructor, for method chaining before PHP 8.4.
     *
     * @param string|array<mixed>|object $state
     */
    public static function build(string|array|object $state, string $model = self::MODEL_LATEST): self
    {
        return new self($state, $model);
    }

    /**
     * Adds a question; its answer comes back under the same id.
     */
    public function ask(string $id, Question $question): self
    {
        $this->questions[$id] = $question;

        return $this;
    }

    /**
     * Asks every question that the attributes on a result class declare.
     *
     * Read the answers back with SystemOneResult::as($class).
     *
     * @param class-string $class A class whose constructor parameters carry question attributes.
     * @see Schema
     */
    public function askFor(string $class): self
    {
        foreach ((new Schema($class))->questions as $id => $question) {
            $this->ask($id, $question);
        }

        return $this;
    }

    /**
     * @param EntryType $instructions
     * @param EntryType $true
     * @param EntryType $false
     * @see Noul
     */
    public function noul(
        string $id,
        string|array|object|null $instructions = null,
        string|array|object|null $true = null,
        string|array|object|null $false = null,
    ): self {
        return $this->ask($id, new Noul($instructions, new NoulCriteria($true, $false)));
    }

    /**
     * @param EntryType $instructions
     * @param array<array-key, EntryType> $criteria
     * @see Choice
     */
    public function choice(string $id, string|array|object|null $instructions, array $criteria): self
    {
        return $this->ask($id, new Choice($instructions, $criteria));
    }

    /**
     * @param EntryType $instructions
     * @param array<EntryType> $levels
     * @see Score
     */
    public function score(string $id, string|array|object|null $instructions, array $levels): self
    {
        return $this->ask($id, new Score($instructions, $levels));
    }
}
