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

// A score question: how frustrated is the customer, from calm to very angry?
//
// Run: TYPESAFE_API_KEY=your-api-key php examples/frustration.php

use TypeSafeAI\SystemOneRequest;
use TypeSafeAI\TypeSafeClient;

require 'vendor/autoload.php';

$client = TypeSafeClient::createInstance();

$response = $client->systemOne(
    SystemOneRequest::build('Help! My payouts have been failing for 3 days.')
        ->score('frustration', 'How frustrated is the customer?', ['Calm', 'Frustrated', 'Very angry']),
);

$frustration = $response->score('frustration');

// The score is a probability-weighted level, so its value can be between two levels
printf("Frustration: %.2f of %d (confidence %.2f)\n", $frustration->score, count($frustration->legend) - 1, $frustration->confidence);

// The description could be JSON, if that was initially provided
foreach ($frustration->legend as $level => $description) {
    printf(
        "  %d %-12s %.2f\n",
        $level,
        is_string($description) ? $description : json_encode($description),
        $frustration->probabilities[$level],
    );
}
