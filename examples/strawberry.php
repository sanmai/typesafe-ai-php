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

use TypeSafeAI\DTO\ChoiceAnswer;
use TypeSafeAI\OpenAICompatClient;
use TypeSafeAI\Question\Choice;
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

$client = TypeSafeClient::createInstance();
$response = $client->evaluate("Strawberry", StrawberryRs::class);

echo "(Jev)  R's in Strawberry: {$response->rs->choice} (P={$response->rs->confidence})\n";

foreach ($response->rs->probabilities as $option => $probability) {
    echo "{$option}: {$probability}\n";
}

$client = OpenAICompatClient::createInstance(getenv('LLAMA_CPP_URL') ?: 'http://127.0.0.1:8080/v1');

$response = $client->evaluate("Strawberry", StrawberryRs::class);

echo "(Qwen) R's in Strawberry: {$response->rs->choice} (P={$response->rs->confidence})\n";

foreach ($response->rs->probabilities as $option => $probability) {
    echo "{$option}: {$probability}\n";
}
