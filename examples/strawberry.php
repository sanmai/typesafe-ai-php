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

// The same question for the TypeSafe API and for a local model, with the time of each request.
//
// Run: TYPESAFE_API_KEY=your-api-key OPENAI_BASE_URL=http://127.0.0.1:8080/v1 php examples/strawberry.php

use TypeSafeAI\DTO\ChoiceAnswer;
use TypeSafeAI\OpenAICompatClient;
use TypeSafeAI\Question\Choice;
use TypeSafeAI\SystemOneClient;
use TypeSafeAI\TypeSafeClient;

require 'vendor/autoload.php';

class StrawberryRs
{
    public function __construct(
        #[Choice("How many r's in this word?", [
            "1" => "one",
            "2" => "two",
            "3" => "three",
            "4" => "four",
        ])]
        public readonly ChoiceAnswer $rs,
    ) {}
}

/** @var array<string, SystemOneClient> $clients */
$clients = [
    'Jev' => TypeSafeClient::createInstance(),
    'llama' => OpenAICompatClient::createInstance(),
];

foreach ($clients as $name => $client) {
    $time = -microtime(true);
    $response = $client->evaluate("Strawberry", StrawberryRs::class);
    $time += microtime(true);
    $time = sprintf('%.4f', $time);

    echo "($name) R's in Strawberry: {$response->rs->choice} (P={$response->rs->confidence}, t=$time)\n";
    echo json_encode($response->rs->probabilities), "\n";
}
