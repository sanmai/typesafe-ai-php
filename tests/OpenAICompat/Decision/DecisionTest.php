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

namespace Tests\TypeSafeAI\OpenAICompat\Decision;

use Closure;

use function get_object_vars;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

use JSONSerializer\Serializer;
use PHPUnit\Framework\TestCase;
use TypeSafeAI\OpenAICompat\Decision\ChoiceDecision;
use TypeSafeAI\OpenAICompat\Decision\NoulDecision;
use TypeSafeAI\OpenAICompat\Decision\ScoreDecision;
use TypeSafeAI\OpenAICompat\ValueFormatter;
use TypeSafeAI\Question\Choice;
use TypeSafeAI\Question\Noul;
use TypeSafeAI\Question\NoulCriteria;
use TypeSafeAI\Question\Score;

/**
 * @covers \TypeSafeAI\OpenAICompat\Decision\ChoiceDecision
 * @covers \TypeSafeAI\OpenAICompat\Decision\NoulDecision
 * @covers \TypeSafeAI\OpenAICompat\Decision\ScoreDecision
 * @covers \TypeSafeAI\OpenAICompat\ValueFormatter
 */
class DecisionTest extends TestCase
{
    public static function providePrompts(): iterable
    {
        // The rendered examples from the specification
        yield 'choice' => [
            static fn(ValueFormatter $formatter) => new ChoiceDecision(new Choice('Select the primary requested action. A mention without a request does not establish intent.', [
                'cancel' => 'End an existing subscription',
                'refund' => 'Return money already charged',
                'status' => 'Learn delivery progress',
                'change_address' => 'Modify a delivery address',
                'other' => 'None of these actions is requested',
            ]), $formatter),
            ['cancel', 'refund', 'status', 'change_address', 'other'],
            "Select the primary requested action. A mention without a request does not establish intent.\n\nOptions:\n- cancel: End an existing subscription\n- refund: Return money already charged\n- status: Learn delivery progress\n- change_address: Modify a delivery address\n- other: None of these actions is requested",
        ];

        yield 'score' => [
            static fn(ValueFormatter $formatter) => new ScoreDecision(new Score('Rate incident impact using only reported facts. Use the highest fully supported level.', [
                'No function impaired; cosmetic only',
                'One user or a nonessential function impaired, with a workaround',
                'Many users blocked from a core function, no data loss',
                'Confirmed irreversible data loss or physical harm',
            ]), $formatter),
            ['0', '1', '2', '3'],
            "Rate incident impact using only reported facts. Use the highest fully supported level.\n\nLevels:\n0: No function impaired; cosmetic only\n1: One user or a nonessential function impaired, with a workaround\n2: Many users blocked from a core function, no data loss\n3: Confirmed irreversible data loss or physical harm",
        ];

        yield 'noul' => [
            static fn(ValueFormatter $formatter) => new NoulDecision(new Noul('Is the action permitted?', new NoulCriteria(true: 'Every condition holds', false: 'A condition is missing')), $formatter),
            ['no', 'yes'],
            "Is the action permitted?\n\nOptions:\n- no: A condition is missing\n- yes: Every condition holds",
        ];

        yield 'noul without criteria' => [
            static fn(ValueFormatter $formatter) => new NoulDecision(new Noul('Is it urgent?'), $formatter),
            ['no', 'yes'],
            "Is it urgent?\n\nOptions:\n- no\n- yes",
        ];

        yield 'structured values' => [
            static fn(ValueFormatter $formatter) => new ChoiceDecision(new Choice(['ask' => 'Which team?'], [
                'billing' => ['what' => 'Счета / charges'],
                'technical' => null,
                'other' => '',
                'ключ' => 'Non-ASCII label',
                'n/a' => null,
                '42' => 'Numeric label',
            ]), $formatter),
            ['billing', 'technical', 'other', 'ключ', 'n/a', '42'],
            "{\"ask\":\"Which team?\"}\n\nOptions:\n- billing: {\"what\":\"Счета / charges\"}\n- technical\n- other\n- ключ: Non-ASCII label\n- n/a\n- 42: Numeric label",
        ];

        yield 'score with keys' => [
            static fn(ValueFormatter $formatter) => new ScoreDecision(new Score('How loud?', ['low' => 'Quiet', 'high' => 'Loud']), $formatter),
            ['0', '1'],
            "How loud?\n\nLevels:\n0: Quiet\n1: Loud",
        ];

        yield 'score without instructions' => [
            static fn(ValueFormatter $formatter) => new ScoreDecision(new Score(null, ['Calm', ['level' => 'Angry']]), $formatter),
            ['0', '1'],
            "\n\nLevels:\n0: Calm\n1: {\"level\":\"Angry\"}",
        ];
    }

    private static function formatter(): ValueFormatter
    {
        return new ValueFormatter(Serializer::withJSONOptions(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @dataProvider providePrompts
     * @param Closure(ValueFormatter): \TypeSafeAI\OpenAICompat\Decision $decision
     */
    public function testPrompt(Closure $decision, array $labels, string $expected): void
    {
        $decision = $decision(self::formatter());

        $this->assertSame($labels, $decision->labels());
        $this->assertSame($expected, $decision->prompt());
    }

    public function testNoulAnswer(): void
    {
        $decision = new NoulDecision(new Noul('Is it urgent?'), self::formatter());

        $this->assertSame(
            ['type' => 'noul', 'noul' => 0.9],
            get_object_vars($decision->answer(['no' => 0.1, 'yes' => 0.9])),
        );
    }

    public function testChoiceAnswer(): void
    {
        $decision = new ChoiceDecision(new Choice('Which team?', ['billing' => null, 'technical' => null]), self::formatter());

        $this->assertSame(
            ['type' => 'choice', 'choice' => 'technical', 'probabilities' => ['technical' => 0.7, 'billing' => 0.3], 'confidence' => 0.7],
            get_object_vars($decision->answer(['technical' => 0.7, 'billing' => 0.3])),
        );
    }

    public function testScoreAnswer(): void
    {
        $decision = new ScoreDecision(new Score('How frustrated?', ['Calm', 'Frustrated', 'Very angry']), self::formatter());

        $this->assertSame(
            [
                'type' => 'score',
                'score' => 1.3,
                'legend' => ['Calm', 'Frustrated', 'Very angry'],
                'probabilities' => [0.1, 0.5, 0.4],
                'confidence' => 0.5,
            ],
            get_object_vars($decision->answer([0.1, 0.5, 0.4])),
        );
    }
}
