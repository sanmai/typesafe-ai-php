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

use function array_fill_keys;
use function array_keys;
use function array_map;

use TypeSafeAI\OpenAICompat\Decision\Decision;

/**
 * Builds the JSON schema of an object that maps each question ID to a probability for each label.
 *
 * @final
 */
class Schema
{
    /**
     * @param array<array-key, Decision> $decisions
     * @return array<string, mixed>
     */
    public static function of(array $decisions): array
    {
        return self::object(array_map(
            static fn(Decision $decision) => self::object(array_fill_keys($decision->labels(), ['type' => 'number'])),
            $decisions,
        ));
    }

    /**
     * @param array<array-key, mixed> $properties
     * @return array<string, mixed>
     */
    private static function object(array $properties): array
    {
        return [
            'type' => 'object',
            // An object also for numeric keys, such as the level indices of a score
            'properties' => (object) $properties,
            'required' => array_map(strval(...), array_keys($properties)),
            'additionalProperties' => false,
        ];
    }
}
