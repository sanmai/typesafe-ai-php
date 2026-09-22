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

// A result class that declares its own questions, populated with a typed result for each one.
//
// Run: TYPESAFE_API_KEY=your-api-key php examples/attributes.php

use TypeSafeAI\DTO\ChoiceAnswer;
use TypeSafeAI\DTO\NoulAnswer;
use TypeSafeAI\DTO\ScoreAnswer;
use TypeSafeAI\Question\Choice;
use TypeSafeAI\Question\Noul;
use TypeSafeAI\Question\NoulCriteria;
use TypeSafeAI\Question\Score;
use TypeSafeAI\TypeSafeClient;

require 'vendor/autoload.php';

class TicketDecision
{
    public function __construct(
        #[Noul('Does this convey urgency?', new NoulCriteria(true: 'Explicitly time-sensitive', false: 'No urgency expressed'))]
        public readonly NoulAnswer $is_urgent,
        #[Choice('Which team should handle this?', [
            'billing' => 'Payments, invoicing, refunds',
            'technical' => 'Bugs, outages, integrations',
            'sales' => 'Pricing, upgrades, new accounts',
        ])]
        public readonly ChoiceAnswer $department,
        #[Score('How frustrated is the customer?', ['Calm', 'Frustrated', 'Very angry'])]
        public readonly ScoreAnswer $frustration,
    ) {}
}

$client = TypeSafeClient::createInstance();

$decision = $client->evaluate(
    'Help! My payouts have been failing for 3 days.',
    TicketDecision::class,
);

printf("Urgent:      %.2f\n", $decision->is_urgent->noul);
printf("Route to:    %s (confidence %.2f)\n", $decision->department->choice, $decision->department->confidence);
printf("Frustration: %.2f\n", $decision->frustration->score);
