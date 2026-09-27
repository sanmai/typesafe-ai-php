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

// The questions from attributes.php, evaluated by a local model with llama.cpp.
//
// Run: LLAMA_CPP_URL=http://127.0.0.1:8080/v1 php examples/llama-cpp.php

use TypeSafeAI\DTO\ChoiceAnswer;
use TypeSafeAI\DTO\NoulAnswer;
use TypeSafeAI\DTO\ScoreAnswer;
use TypeSafeAI\OpenAICompatClient;
use TypeSafeAI\Question\Choice;
use TypeSafeAI\Question\Noul;
use TypeSafeAI\Question\NoulCriteria;
use TypeSafeAI\Question\Score;

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

$client = OpenAICompatClient::createInstance(getenv('LLAMA_CPP_URL') ?: 'http://127.0.0.1:8080/v1');

$decision = $client->evaluate(
    'Help! My payouts have been failing for 3 days.',
    TicketDecision::class,
);

printf("Urgent:      %.2f\n", $decision->is_urgent->noul);
printf("Department:  %s (%.2f)\n", $decision->department->choice, $decision->department->confidence);
printf("Frustration: %.2f of 2\n", $decision->frustration->score);
