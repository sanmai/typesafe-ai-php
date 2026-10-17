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

namespace TypeSafeAI\OpenAICompat\DTO;

use JSONSerializer\Contracts\ItemMap;

/**
 * This class maps each question ID to the probability of each label.
 *
 * @final
 */
class Distributions implements ItemMap
{
    /**
     * @param array<array-key, non-empty-array<array-key, float>> $distributions
     */
    private function __construct(
        private readonly array $distributions,
    ) {}

    public static function getKeyType(): string
    {
        return 'string';
    }

    public static function getItemType(): string
    {
        return 'array<string, float>';
    }

    /**
     * @param array<array-key, non-empty-array<array-key, float>> $map
     */
    public static function withMap(array $map): self
    {
        return new self($map);
    }

    /**
     * @return non-empty-array<array-key, float> Each label mapped to its probability.
     */
    public function probabilities(int|string $id): array
    {
        return $this->distributions[$id];
    }
}
