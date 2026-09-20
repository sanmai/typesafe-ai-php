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

// A yes/no question: does the message convey urgency?
//
// Run: TYPESAFE_AI=your-api-key php examples/urgency.php

use TypeSafeAI\SystemOneRequest;
use TypeSafeAI\TypeSafeClient;

require 'vendor/autoload.php';

$client = TypeSafeClient::createInstance(getenv('TYPESAFE_AI') ?: exit("Set the TYPESAFE_AI environment variable.\n"));

$response = $client->systemOne(
    SystemOneRequest::build('Help! My payouts have been failing for 3 days.')
        ->noul(
            'is_urgent',
            'Does this convey urgency?',
            true: 'Explicitly time-sensitive',
            false: 'No urgency expressed',
        ),
);

$urgency = $response->noul('is_urgent')->noul;

printf("Probability of urgency: %.2f\n", $urgency);
echo $urgency > 0.5 ? "Escalate now.\n" : "Queue as usual.\n";
