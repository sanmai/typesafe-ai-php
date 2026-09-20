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

namespace Tests\TypeSafeAI;

use TypeSafeAI\SystemOneRequest;
use Tests\TypeSafeAI\Doubles\ExampleState;
use TypeSafeAI\Question\Noul;
use TypeSafeAI\Question\NoulCriteria;

use function json_decode;

/**
 * @covers \TypeSafeAI\SystemOneRequest
 * @covers \TypeSafeAI\Question\Noul
 * @covers \TypeSafeAI\Question\NoulCriteria
 * @covers \TypeSafeAI\Question\Choice
 * @covers \TypeSafeAI\Question\Score
 */
class SystemOneRequestTest extends TestCase
{
    private const STATE = 'Help! My payouts have been failing for 3 days.';

    public static function provideRequests(): iterable
    {
        yield 'noul' => [
            fn() => SystemOneRequest::build(self::STATE)
                ->noul('is_urgent', 'Does this convey urgency?'),
            '{
              "state": "Help! My payouts have been failing for 3 days.",
              "model": "jev-latest",
              "questions": {
                "is_urgent": {
                  "type": "noul",
                  "instructions": "Does this convey urgency?"
                }
              }
            }',
        ];

        yield 'noul with criteria' => [
            fn() => SystemOneRequest::build(self::STATE)
                ->noul('is_urgent', 'Does this convey urgency?', true: 'Explicitly time-sensitive', false: 'No urgency expressed'),
            '{
              "state": "Help! My payouts have been failing for 3 days.",
              "model": "jev-latest",
              "questions": {
                "is_urgent": {
                  "type": "noul",
                  "instructions": "Does this convey urgency?",
                  "criteria": {
                    "true": "Explicitly time-sensitive",
                    "false": "No urgency expressed"
                  }
                }
              }
            }',
        ];

        yield 'noul with one criterion' => [
            fn() => SystemOneRequest::build(self::STATE)
                ->noul('is_urgent', 'Does this convey urgency?', false: 'No urgency expressed'),
            '{
              "state": "Help! My payouts have been failing for 3 days.",
              "model": "jev-latest",
              "questions": {
                "is_urgent": {
                  "type": "noul",
                  "instructions": "Does this convey urgency?",
                  "criteria": {
                    "false": "No urgency expressed"
                  }
                }
              }
            }',
        ];

        yield 'choice' => [
            fn() => SystemOneRequest::build(self::STATE)
                ->choice('department', 'Which team should handle this?', [
                    'billing' => 'Payments, invoicing, refunds',
                    'technical' => 'Bugs, outages, integrations',
                    'sales' => null,
                ]),
            '{
              "state": "Help! My payouts have been failing for 3 days.",
              "model": "jev-latest",
              "questions": {
                "department": {
                  "type": "choice",
                  "instructions": "Which team should handle this?",
                  "criteria": {
                    "billing": "Payments, invoicing, refunds",
                    "technical": "Bugs, outages, integrations",
                    "sales": null
                  }
                }
              }
            }',
        ];

        yield 'choice with numeric options' => [
            fn() => SystemOneRequest::build(self::STATE)
                ->choice('tier', 'Which support tier?', ['0' => 'Self-service', '1' => 'Priority']),
            '{
              "state": "Help! My payouts have been failing for 3 days.",
              "model": "jev-latest",
              "questions": {
                "tier": {
                  "type": "choice",
                  "instructions": "Which support tier?",
                  "criteria": {"0": "Self-service", "1": "Priority"}
                }
              }
            }',
        ];

        yield 'choice without options' => [
            fn() => SystemOneRequest::build(self::STATE)
                ->choice('department', 'Which team should handle this?', []),
            '{
              "state": "Help! My payouts have been failing for 3 days.",
              "model": "jev-latest",
              "questions": {
                "department": {
                  "type": "choice",
                  "instructions": "Which team should handle this?",
                  "criteria": {}
                }
              }
            }',
        ];

        yield 'score' => [
            fn() => SystemOneRequest::build(self::STATE)
                ->score('frustration', 'How frustrated is the customer?', ['low' => 'Calm', 'mid' => 'Frustrated', 'high' => 'Very angry']),
            '{
              "state": "Help! My payouts have been failing for 3 days.",
              "model": "jev-latest",
              "questions": {
                "frustration": {
                  "type": "score",
                  "instructions": "How frustrated is the customer?",
                  "criteria": ["Calm", "Frustrated", "Very angry"]
                }
              }
            }',
        ];

        yield 'score with one level' => [
            fn() => SystemOneRequest::build(self::STATE)
                ->score('frustration', 'Is the customer frustrated?', ['Calm']),
            '{
              "state": "Help! My payouts have been failing for 3 days.",
              "model": "jev-latest",
              "questions": {
                "frustration": {
                  "type": "score",
                  "instructions": "Is the customer frustrated?",
                  "criteria": ["Calm"]
                }
              }
            }',
        ];

        yield 'structured state, numeric ids, other model' => [
            fn() => SystemOneRequest::build([['role' => 'user', 'content' => 'Hi']], 'jev-other')
                ->ask('0', new Noul(['question' => 'Is this a greeting?'])),
            '{
              "state": [{"role": "user", "content": "Hi"}],
              "model": "jev-other",
              "questions": {
                "0": {
                  "type": "noul",
                  "instructions": {"question": "Is this a greeting?"}
                }
              }
            }',
        ];

        yield 'constructor' => [
            fn() => new SystemOneRequest(self::STATE, questions: [
                'is_urgent' => new Noul('Does this convey urgency?', new NoulCriteria(true: 'Explicitly time-sensitive')),
            ]),
            '{
              "state": "Help! My payouts have been failing for 3 days.",
              "model": "jev-latest",
              "questions": {
                "is_urgent": {
                  "type": "noul",
                  "instructions": "Does this convey urgency?",
                  "criteria": {"true": "Explicitly time-sensitive"}
                }
              }
            }',
        ];

        yield 'state with nulls' => [
            fn() => SystemOneRequest::build(['ticket' => ['id' => 42, 'assignee' => null, 'tags' => []]]),
            '{
              "state": {"ticket": {"id": 42, "assignee": null, "tags": []}},
              "model": "jev-latest",
              "questions": {}
            }',
        ];

        yield 'state as stdClass' => [
            fn() => SystemOneRequest::build((object) ['id' => 42, 'assignee' => null]),
            '{
              "state": {"id": 42, "assignee": null},
              "model": "jev-latest",
              "questions": {}
            }',
        ];

        yield 'state as an object' => [
            fn() => SystemOneRequest::build(new ExampleState()),
            '{
              "state": {"id": 42, "assignee": null, "secret": "private properties go out too"},
              "model": "jev-latest",
              "questions": {}
            }',
        ];

        yield 'no questions' => [
            fn() => SystemOneRequest::build(self::STATE),
            '{
              "state": "Help! My payouts have been failing for 3 days.",
              "model": "jev-latest",
              "questions": {}
            }',
        ];
    }

    /**
     * @dataProvider provideRequests
     */
    public function testJson(callable $build, string $expected): void
    {
        $this->clientWith([self::success()])->systemOne($build());

        // Decoded JSON objects: key order does not matter, but {} and [], or a missing key and null, differ
        $this->assertEquals(json_decode($expected), json_decode((string) $this->getLastRequest()->getBody()));
    }
}
