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
use TypeSafeAI\DTO\Answer;
use TypeSafeAI\DTO\ChoiceAnswer;
use TypeSafeAI\DTO\NoulAnswer;
use TypeSafeAI\DTO\ScoreAnswer;
use TypeSafeAI\DTO\Usage;
use UnexpectedValueException;

use function get_debug_type;
use function sprintf;

class SystemOneResult
{
    /**
     * The model that performed the evaluation.
     */
    public string $model;

    /**
     * One answer per question, under the same ids as in the request.
     *
     * @var array<string, Answer>
     */
    #[Type('array<string, TypeSafeAI\DTO\Answer>')]
    public array $answers;

    public Usage $usage;

    public function noul(string $id): NoulAnswer
    {
        return $this->answer($id, NoulAnswer::class);
    }

    public function choice(string $id): ChoiceAnswer
    {
        return $this->answer($id, ChoiceAnswer::class);
    }

    public function score(string $id): ScoreAnswer
    {
        return $this->answer($id, ScoreAnswer::class);
    }

    /**
     * @template T of Answer
     * @param class-string<T> $type
     * @return T
     */
    private function answer(string $id, string $type): Answer
    {
        $answer = $this->answers[$id] ?? null;

        if ($answer instanceof $type) {
            return $answer;
        }

        throw new UnexpectedValueException(sprintf('Expected %s for "%s", got %s', $type, $id, get_debug_type($answer)));
    }
}
