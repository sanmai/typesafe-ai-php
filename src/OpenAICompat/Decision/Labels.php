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

use function array_map;
use function implode;
use function json_encode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * @final
 */
class Labels
{
    /**
     * Formats labels as Python's json.dumps() does: ["no", "yes"].
     *
     * @param list<string> $labels
     */
    public static function json(array $labels): string
    {
        return '[' . implode(', ', array_map(
            static fn(string $label) => json_encode($label, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $labels,
        )) . ']';
    }
}
