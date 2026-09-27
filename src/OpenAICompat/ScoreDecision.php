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

namespace TypeSafeAI\OpenAICompat;

use function array_keys;
use function array_map;
use function implode;

/**
 * @final
 */
class ScoreDecision implements Decision
{
    /**
     * @param array<mixed> $levels Level descriptions, from the lowest to the highest.
     */
    public function __construct(
        private readonly mixed $instructions,
        private readonly array $levels,
    ) {}

    public function labels(): array
    {
        return array_map(strval(...), array_keys($this->levels));
    }

    public function prompt(): string
    {
        $lines = [];

        foreach ($this->levels as $level => $description) {
            $lines[] = "$level: " . Text::of($description);
        }

        return Text::of($this->instructions)
            . "\n\nLevels:\n" . implode("\n", $lines)
            . "\n\nRate the state. Output probabilities over the level indices: " . Labels::json($this->labels()) . '.';
    }

    public function answer(Distribution $distribution): array
    {
        $score = 0.0;

        foreach ($distribution->probabilities as $level => $probability) {
            $score += (int) $level * $probability;
        }

        return [
            'type' => 'score',
            'score' => $score,
            'legend' => $this->levels,
            'probabilities' => $distribution->probabilities,
            'confidence' => $distribution->confidence(),
        ];
    }
}
