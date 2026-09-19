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

// Several questions of different types about a structured state (a chat log), in one request.
//
// Run: TYPESAFE_AI=your-api-key php examples/chat-log.php

use TypeSafeAI\EvaluationRequest;
use TypeSafeAI\TypeSafeClient;

require 'vendor/autoload.php';

$client = TypeSafeClient::createInstance(getenv('TYPESAFE_AI') ?: exit("Set the TYPESAFE_AI environment variable.\n"));

$chat = [
    ['role' => 'customer', 'content' => 'I was charged twice for my subscription this month.'],
    ['role' => 'agent', 'content' => 'Sorry about that! I can see both charges. I have refunded the duplicate.'],
    ['role' => 'customer', 'content' => 'Thanks, that was quick.'],
];

$response = $client->evaluate(
    EvaluationRequest::build($chat)
        ->noul('resolved', 'Did the agent resolve the issue?')
        ->choice('topic', 'What was the conversation about?', [
            'billing' => null,
            'technical' => null,
            'account' => 'Login, profile, settings',
        ])
        ->score('satisfaction', 'How satisfied is the customer at the end?', ['Unhappy', 'Neutral', 'Happy']),
);

printf("Resolved:     %.2f\n", $response->noul('resolved')->noul);
printf("Topic:        %s\n", $response->choice('topic')->choice);
printf("Satisfaction: %.2f\n", $response->score('satisfaction')->score);
printf("Tokens:       %d in, %d out\n", $response->usage->input_tokens, $response->usage->output_tokens);
