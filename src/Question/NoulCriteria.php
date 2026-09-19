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
use function is_string;

/**
 * Optional descriptions of what a yes and a no mean.
 */
class NoulCriteria
{
    /**
     * @param string|null $true What a yes (value near 1) means.
     * @param string|null $false What a no (value near 0) means.
     */
    public function __construct(
        #[Exclude]
        public ?string $true = null,
        #[Exclude]
        public ?string $false = null,
    ) {}

    /**
     * The client sends nulls, so this hook leaves out the descriptions that are not set.
     *
     * @return array<string, string>
     */
    #[VirtualProperty]
    #[Inline]
    #[Type('array<string, string>')]
    public function descriptions(): array
    {
        return array_filter(['true' => $this->true, 'false' => $this->false], is_string(...));
    }
}
