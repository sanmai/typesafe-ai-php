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

// A choice question: which team should handle the ticket?
//
// Run: TYPESAFE_AI=your-api-key php examples/routing.php

use TypeSafeAI\EvaluationRequest;
use TypeSafeAI\TypeSafeClient;

require 'vendor/autoload.php';

$client = TypeSafeClient::createInstance(getenv('TYPESAFE_AI') ?: exit("Set the TYPESAFE_AI environment variable.\n"));

$response = $client->evaluate(
    EvaluationRequest::build('Help! My payouts have been failing for 3 days.')
        ->choice('department', 'Which team should handle this?', [
            'billing' => 'Payments, invoicing, refunds',
            'technical' => 'Bugs, outages, integrations',
            'sales' => 'Pricing, upgrades, new accounts',
        ]),
);

$department = $response->choice('department');

printf("Route to: %s (confidence %.2f)\n", $department->choice, $department->confidence);

foreach ($department->probabilities as $option => $probability) {
    printf("  %-10s %.2f\n", $option, $probability);
}
