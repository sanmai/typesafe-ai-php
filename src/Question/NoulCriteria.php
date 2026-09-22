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

use JMS\Serializer\Annotation\Exclude;
use JMS\Serializer\Annotation\Inline;
use JMS\Serializer\Annotation\Type;
use JMS\Serializer\Annotation\VirtualProperty;

use function array_filter;

/**
 * Optional descriptions of what a yes and a no mean.
 *
 * @phpstan-import-type EntryType from Question
 */
class NoulCriteria
{
    /**
     * @param EntryType $true What a yes (value near 1) means.
     * @param EntryType $false What a no (value near 0) means.
     */
    public function __construct(
        #[Exclude]
        public string|array|object|null $true = null,
        #[Exclude]
        public string|array|object|null $false = null,
    ) {}

    /**
     * A hook to omit optional descriptions.
     *
     * @return array<string, EntryType>
     */
    #[VirtualProperty]
    #[Inline]
    #[Type('array<string, union>')]
    public function descriptions(): array
    {
        return array_filter(
            ['true' => $this->true, 'false' => $this->false],
            static fn($description) => null !== $description,
        );
    }
}
