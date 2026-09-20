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

// The SDK does not validate questions; the API does. An invalid request comes back as a 422 error.
//
// Run: TYPESAFE_AI=your-api-key php examples/errors.php

use GuzzleHttp\Exception\ClientException;
use TypeSafeAI\SystemOneRequest;
use TypeSafeAI\TypeSafeClient;

require 'vendor/autoload.php';

$client = TypeSafeClient::createInstance(getenv('TYPESAFE_AI') ?: exit("Set the TYPESAFE_AI environment variable.\n"));

try {
    // A score question needs at least one level
    $client->systemOne(
        SystemOneRequest::build('Help! My payouts have been failing for 3 days.')
            ->score('frustration', 'How frustrated is the customer?', []),
    );
} catch (ClientException $e) {
    printf("HTTP %d: %s\n", $e->getResponse()->getStatusCode(), $e->getResponse()->getBody());
}
