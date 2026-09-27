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
use function array_values;
use function implode;
use function max;

use TypeSafeAI\DTO\ScoreAnswer;
use TypeSafeAI\OpenAICompat\Text;
use TypeSafeAI\Question\Score;
use TypeSafeAI\SystemOneRequest;

/**
 * @phpstan-import-type ValueType from SystemOneRequest
 * @final
 */
class ScoreDecision implements Decision
{
    /**
     * @var list<ValueType> Level descriptions, from the lowest to the highest.
     */
    private readonly array $levels;

    public function __construct(
        private readonly Score $question,
        private readonly Text $text,
    ) {
        $this->levels = array_values($question->criteria);
    }

    public function labels(): array
    {
        return array_map(strval(...), array_keys($this->levels));
    }

    public function prompt(): string
    {
        $lines = [];

        foreach ($this->levels as $level => $description) {
            $lines[] = "$level: " . $this->text->of($description);
        }

        return $this->text->of($this->question->instructions)
            . "\n\nLevels:\n" . implode("\n", $lines);
    }

    public function answer(array $probabilities): ScoreAnswer
    {
        $score = 0.0;
        $levels = [];

        foreach (array_keys($this->levels) as $level) {
            $levels[$level] = $probabilities[$level];
            $score += $level * $levels[$level];
        }

        $answer = new ScoreAnswer();
        $answer->score = $score;
        $answer->legend = $this->levels;
        $answer->probabilities = $levels;
        $answer->confidence = max($probabilities);

        return $answer;
    }
}
