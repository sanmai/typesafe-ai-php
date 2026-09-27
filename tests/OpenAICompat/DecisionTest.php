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

namespace Tests\TypeSafeAI\OpenAICompat;

use PHPUnit\Framework\TestCase;
use TypeSafeAI\OpenAICompat\ChoiceDecision;
use TypeSafeAI\OpenAICompat\Decision;
use TypeSafeAI\OpenAICompat\Distribution;
use TypeSafeAI\OpenAICompat\NoulDecision;
use TypeSafeAI\OpenAICompat\ScoreDecision;

/**
 * @covers \TypeSafeAI\OpenAICompat\ChoiceDecision
 * @covers \TypeSafeAI\OpenAICompat\NoulDecision
 * @covers \TypeSafeAI\OpenAICompat\ScoreDecision
 * @covers \TypeSafeAI\OpenAICompat\Text
 * @covers \TypeSafeAI\OpenAICompat\Labels
 */
class DecisionTest extends TestCase
{
    public static function providePrompts(): iterable
    {
        // The rendered examples from the specification
        yield 'choice' => [
            new ChoiceDecision('Select the primary requested action. A mention without a request does not establish intent.', [
                'cancel' => 'End an existing subscription',
                'refund' => 'Return money already charged',
                'status' => 'Learn delivery progress',
                'change_address' => 'Modify a delivery address',
                'other' => 'None of these actions is requested',
            ]),
            ['cancel', 'refund', 'status', 'change_address', 'other'],
            "Select the primary requested action. A mention without a request does not establish intent.\n\nOptions:\n- cancel: End an existing subscription\n- refund: Return money already charged\n- status: Learn delivery progress\n- change_address: Modify a delivery address\n- other: None of these actions is requested\n\nOutput probabilities over exactly these keys: [\"cancel\", \"refund\", \"status\", \"change_address\", \"other\"].",
        ];

        yield 'score' => [
            new ScoreDecision('Rate incident impact using only reported facts. Use the highest fully supported level.', [
                'No function impaired; cosmetic only',
                'One user or a nonessential function impaired, with a workaround',
                'Many users blocked from a core function, no data loss',
                'Confirmed irreversible data loss or physical harm',
            ]),
            ['0', '1', '2', '3'],
            "Rate incident impact using only reported facts. Use the highest fully supported level.\n\nLevels:\n0: No function impaired; cosmetic only\n1: One user or a nonessential function impaired, with a workaround\n2: Many users blocked from a core function, no data loss\n3: Confirmed irreversible data loss or physical harm\n\nRate the state. Output probabilities over the level indices: [\"0\", \"1\", \"2\", \"3\"].",
        ];

        yield 'noul' => [
            new NoulDecision('Is the action permitted?', ['true' => 'Every condition holds', 'false' => 'A condition is missing']),
            ['no', 'yes'],
            "Is the action permitted?\n\nOptions:\n- no: A condition is missing\n- yes: Every condition holds\n\nOutput probabilities over exactly these keys: [\"no\", \"yes\"].",
        ];

        yield 'noul without criteria' => [
            new NoulDecision('Is it urgent?', []),
            ['no', 'yes'],
            "Is it urgent?\n\nOptions:\n- no\n- yes\n\nOutput probabilities over exactly these keys: [\"no\", \"yes\"].",
        ];

        yield 'structured values' => [
            new ChoiceDecision(['ask' => 'Which team?'], [
                'billing' => ['what' => 'Счета / charges'],
                'technical' => null,
                'other' => '',
                'ключ' => 'Non-ASCII label',
                'n/a' => null,
                '42' => 'Numeric label',
            ]),
            ['billing', 'technical', 'other', 'ключ', 'n/a', '42'],
            "{\"ask\":\"Which team?\"}\n\nOptions:\n- billing: {\"what\":\"Счета / charges\"}\n- technical\n- other\n- ключ: Non-ASCII label\n- n/a\n- 42: Numeric label\n\nOutput probabilities over exactly these keys: [\"billing\", \"technical\", \"other\", \"\\u043a\\u043b\\u044e\\u0447\", \"n/a\", \"42\"].",
        ];

        yield 'score without instructions' => [
            new ScoreDecision(null, ['Calm', ['level' => 'Angry']]),
            ['0', '1'],
            "\n\nLevels:\n0: Calm\n1: {\"level\":\"Angry\"}\n\nRate the state. Output probabilities over the level indices: [\"0\", \"1\"].",
        ];
    }

    /**
     * @dataProvider providePrompts
     */
    public function testPrompt(Decision $decision, array $labels, string $expected): void
    {
        $this->assertSame($labels, $decision->labels());
        $this->assertSame($expected, $decision->prompt());
    }

    public function testNoulAnswer(): void
    {
        $decision = new NoulDecision('Is it urgent?', []);

        $this->assertSame(
            ['type' => 'noul', 'noul' => 0.9],
            $decision->answer(Distribution::parse('{"probabilities": {"no": 0.1, "yes": 0.9}}', $decision->labels())),
        );
    }

    public function testChoiceAnswer(): void
    {
        $decision = new ChoiceDecision('Which team?', ['billing' => null, 'technical' => null]);

        $this->assertSame(
            ['type' => 'choice', 'choice' => 'technical', 'probabilities' => ['billing' => 0.3, 'technical' => 0.7], 'confidence' => 0.7],
            $decision->answer(Distribution::parse('{"probabilities": {"technical": 0.7, "billing": 0.3}}', $decision->labels())),
        );
    }

    public function testScoreAnswer(): void
    {
        $decision = new ScoreDecision('How frustrated?', ['Calm', 'Frustrated', 'Very angry']);

        $this->assertSame(
            [
                'type' => 'score',
                'score' => 1.3,
                'legend' => ['Calm', 'Frustrated', 'Very angry'],
                'probabilities' => [0.1, 0.5, 0.4],
                'confidence' => 0.5,
            ],
            $decision->answer(Distribution::parse('{"probabilities": {"0": 0.1, "1": 0.5, "2": 0.4}}', $decision->labels())),
        );
    }
}
