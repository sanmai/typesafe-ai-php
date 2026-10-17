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

namespace TypeSafeAI\OpenAICompat\Decision;

use function array_keys;
use function array_map;
use function array_search;
use function implode;
use function max;

use TypeSafeAI\DTO\ChoiceAnswer;
use TypeSafeAI\OpenAICompat\ValueFormatter;
use TypeSafeAI\Question\Choice;

/**
 * @final
 */
class ChoiceDecision implements Decision
{
    public function __construct(
        private readonly Choice $question,
        private readonly ValueFormatter $formatter,
    ) {}

    public function labels(): array
    {
        return array_map(strval(...), array_keys($this->question->criteria));
    }

    public function prompt(): string
    {
        $lines = [];

        foreach ($this->question->criteria as $label => $description) {
            $text = $this->formatter->format($description);
            $lines[] = '' === $text ? "- $label" : "- $label: $text";
        }

        return $this->formatter->format($this->question->instructions)
            . "\n\nOptions:\n" . implode("\n", $lines);
    }

    public function answer(array $probabilities): ChoiceAnswer
    {
        $answer = new ChoiceAnswer();
        $answer->confidence = max($probabilities);
        $answer->choice = (string) array_search($answer->confidence, $probabilities, true);
        $answer->probabilities = $probabilities;

        return $answer;
    }
}
