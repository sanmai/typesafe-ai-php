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

namespace Tests\TypeSafeAI\Doubles;

use JsonSerializable;
use BadMethodCallException;

/**
 * Used to validate that JsonSerializable is unused.
 */
class ExampleState implements JsonSerializable
{
    public int $id = 42;

    public ?string $assignee = null;

    private string $secret = 'private properties are sent too';

    public function jsonSerialize(): mixed
    {
        throw new BadMethodCallException();
    }
}
