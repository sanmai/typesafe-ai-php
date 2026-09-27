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
class ChoiceDecision implements Decision
{
    /**
     * @param array<array-key, mixed> $criteria Options mapped to their descriptions.
     */
    public function __construct(
        private readonly mixed $instructions,
        private readonly array $criteria,
    ) {}

    public function labels(): array
    {
        return array_map(strval(...), array_keys($this->criteria));
    }

    public function prompt(): string
    {
        $lines = [];

        foreach ($this->criteria as $label => $description) {
            $text = Text::of($description);
            $lines[] = '' === $text ? "- $label" : "- $label: $text";
        }

        return Text::of($this->instructions)
            . "\n\nOptions:\n" . implode("\n", $lines)
            . "\n\nOutput probabilities over exactly these keys: " . Labels::json($this->labels()) . '.';
    }

    public function answer(Distribution $distribution): array
    {
        return [
            'type' => 'choice',
            'choice' => $distribution->argmax(),
            'probabilities' => $distribution->probabilities,
            'confidence' => $distribution->confidence(),
        ];
    }
}
