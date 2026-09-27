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

namespace TypeSafeAI;

use ReflectionClass;

/**
 * @phpstan-import-type ValueType from SystemOneRequest
 */
trait SystemOneEvaluator
{
    abstract public function systemOne(SystemOneRequest $request): SystemOneResult;

    /**
     * Evaluates the questions declared by a class and returns an instance with the answers.
     *
     * @template TResult of object
     * @param ValueType $state
     * @param class-string<TResult> $class
     * @return TResult
     */
    public function evaluate(string|array|object $state, string $class, string $model = SystemOneRequest::MODEL_LATEST): object
    {
        $reader = new AttributeReader(new ReflectionClass($class));
        $request = new SystemOneRequest($state, $model, [...$reader->questions()]);

        return $reader->hydrate($this->systemOne($request));
    }
}
