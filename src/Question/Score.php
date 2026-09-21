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
 * Rates the state along the ordered levels you define.
 *
 * @phpstan-import-type EntryType from Question
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class Score implements Question
{
    public string $type = 'score';

    /**
     * @param EntryType $instructions
     * @param array<EntryType> $criteria Level descriptions, from the lowest to the highest.
     */
    public function __construct(
        public string|array|object|null $instructions = null,
        #[Type('array<union>')]
        public array $criteria = [],
    ) {}
}
