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

use JMS\Serializer\Annotation\SkipWhenEmpty;

/**
 * A yes/no question. The answer is the probability of yes.
 *
 * @phpstan-import-type EntryType from Question
 */
class Noul implements Question
{
    public string $type = 'noul';

    /**
     * @param EntryType $instructions
     */
    public function __construct(
        public string|array|object|null $instructions = null,
        #[SkipWhenEmpty]
        public NoulCriteria $criteria = new NoulCriteria(),
    ) {}
}
