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

use function get_object_vars;

use JSONSerializer\Serializer;
use PHPUnit\Framework\TestCase;
use TypeSafeAI\OpenAICompat\Decision\ChoiceDecision;
use TypeSafeAI\OpenAICompat\ValueFormatter;
use TypeSafeAI\Question\Choice;

/**
 * @covers \TypeSafeAI\OpenAICompat\Decision\ChoiceDecision
 */
class ChoiceDecisionTest extends TestCase
{
    public static function providePrompts(): iterable
    {
        // The rendered example from the specification
        yield 'specification' => [
            new Choice('Select the primary requested action. A mention without a request does not establish intent.', [
                'cancel' => 'End an existing subscription',
                'refund' => 'Return money already charged',
                'status' => 'Learn delivery progress',
                'change_address' => 'Modify a delivery address',
                'other' => 'None of these actions is requested',
            ]),
            ['cancel', 'refund', 'status', 'change_address', 'other'],
            "Select the primary requested action. A mention without a request does not establish intent.\n\nOptions:\n- cancel: End an existing subscription\n- refund: Return money already charged\n- status: Learn delivery progress\n- change_address: Modify a delivery address\n- other: None of these actions is requested",
        ];

        yield 'structured values' => [
            new Choice(['ask' => 'Which team?'], [
                'billing' => ['what' => 'Charges'],
                'technical' => null,
                'other' => '',
                'ключ' => 'Non-ASCII label',
                'n/a' => null,
                '42' => 'Numeric label',
            ]),
            ['billing', 'technical', 'other', 'ключ', 'n/a', '42'],
            "{\"ask\":\"Which team?\"}\n\nOptions:\n- billing: {\"what\":\"Charges\"}\n- technical\n- other\n- ключ: Non-ASCII label\n- n/a\n- 42: Numeric label",
        ];
    }

    /**
     * @dataProvider providePrompts
     */
    public function testPrompt(Choice $question, array $labels, string $expected): void
    {
        $decision = new ChoiceDecision($question, new ValueFormatter(Serializer::withJSONOptions()));

        $this->assertSame($labels, $decision->labels());
        $this->assertSame($expected, $decision->prompt());
    }

    public static function provideAnswers(): iterable
    {
        yield 'highest' => [
            ['technical' => 0.7, 'billing' => 0.3],
            ['type' => 'choice', 'choice' => 'technical', 'probabilities' => ['technical' => 0.7, 'billing' => 0.3], 'confidence' => 0.7],
        ];

        yield 'tie selects the first option in the response' => [
            ['technical' => 0.5, 'billing' => 0.5],
            ['type' => 'choice', 'choice' => 'technical', 'probabilities' => ['technical' => 0.5, 'billing' => 0.5], 'confidence' => 0.5],
        ];

        yield 'numeric label' => [
            [42 => 0.6, 'other' => 0.4],
            ['type' => 'choice', 'choice' => '42', 'probabilities' => [42 => 0.6, 'other' => 0.4], 'confidence' => 0.6],
        ];
    }

    /**
     * @dataProvider provideAnswers
     */
    public function testAnswer(array $probabilities, array $expected): void
    {
        $decision = new ChoiceDecision(new Choice('Which team?', ['billing' => null, 'technical' => null]), new ValueFormatter(Serializer::withJSONOptions()));

        $this->assertSame($expected, get_object_vars($decision->answer($probabilities)));
    }
}
