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

/**
 * TypeSafe AI API Client.
 */
class TypeSafeClient extends SystemOneHttpClient
{
    public const BASE_URI = 'https://api.typesafe.ai';

    public const API_KEY_ENV = 'TYPESAFE_API_KEY';

    public const BASE_URL_ENV = 'TYPESAFE_BASE_URL';

    public const MODEL_LATEST = 'jev-latest';

    /**
     * The TypeSafe API requires a model: a request without one gets MODEL_LATEST.
     */
    public function systemOne(SystemOneRequest $request): SystemOneResult
    {
        $request = clone $request;
        $request->model ??= self::MODEL_LATEST;

        return parent::systemOne($request);
    }
}
