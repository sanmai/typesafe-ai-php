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

namespace TypeSafeAI\Question;

use Attribute;
use JMS\Serializer\Annotation\Type;

/**
 * Selects one option from the set.
 *
 * @phpstan-import-type EntryType from Question
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class Choice implements Question
{
    public string $type = 'choice';

    /**
     * @param EntryType $instructions
     * @param array<array-key, EntryType> $criteria Options mapped to their descriptions; null when an option needs no description.
     */
    public function __construct(
        public string|array|object|null $instructions = null,
        #[Type('array<string, union>')]
        public array $criteria = [],
    ) {}
}
