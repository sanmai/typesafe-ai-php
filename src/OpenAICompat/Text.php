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

use JMS\Serializer\SerializerInterface;
use TypeSafeAI\RequestContext;

use function is_string;

/**
 * Converts values to prompt text.
 *
 * @final
 */
class Text
{
    public function __construct(private readonly SerializerInterface $serializer) {}

    /**
     * Returns text unchanged, null as an empty string, and structured data as JSON, serialized as TypeSafeClient sends it.
     */
    public function of(mixed $value): string
    {
        return match (true) {
            is_string($value) => $value,
            null === $value => '',
            default => $this->serializer->serialize($value, 'json', RequestContext::create()),
        };
    }
}
