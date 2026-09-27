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
use TypeSafeAI\OpenAICompat\Decision\ScoreDecision;
use TypeSafeAI\OpenAICompat\ValueFormatter;
use TypeSafeAI\Question\Score;

/**
 * @covers \TypeSafeAI\OpenAICompat\Decision\ScoreDecision
 */
class ScoreDecisionTest extends TestCase
{
    public static function providePrompts(): iterable
    {
        // The rendered example from the specification
        yield 'specification' => [
            new Score('Rate incident impact using only reported facts. Use the highest fully supported level.', [
                'No function impaired; cosmetic only',
                'One user or a nonessential function impaired, with a workaround',
                'Many users blocked from a core function, no data loss',
                'Confirmed irreversible data loss or physical harm',
            ]),
            ['0', '1', '2', '3'],
            "Rate incident impact using only reported facts. Use the highest fully supported level.\n\nLevels:\n0: No function impaired; cosmetic only\n1: One user or a nonessential function impaired, with a workaround\n2: Many users blocked from a core function, no data loss\n3: Confirmed irreversible data loss or physical harm",
        ];

        yield 'keys' => [
            new Score('How loud?', ['low' => 'Quiet', 'high' => 'Loud']),
            ['0', '1'],
            "How loud?\n\nLevels:\n0: Quiet\n1: Loud",
        ];

        yield 'no instructions' => [
            new Score(null, ['Calm', ['level' => 'Angry']]),
            ['0', '1'],
            "\n\nLevels:\n0: Calm\n1: {\"level\":\"Angry\"}",
        ];
    }

    /**
     * @dataProvider providePrompts
     */
    public function testPrompt(Score $question, array $labels, string $expected): void
    {
        $decision = new ScoreDecision($question, new ValueFormatter(Serializer::withJSONOptions()));

        $this->assertSame($labels, $decision->labels());
        $this->assertSame($expected, $decision->prompt());
    }

    public static function provideAnswers(): iterable
    {
        yield 'list' => [
            new Score('How frustrated?', ['Calm', 'Frustrated', 'Very angry']),
            [0.1, 0.5, 0.4],
            ['type' => 'score', 'score' => 1.3, 'legend' => ['Calm', 'Frustrated', 'Very angry'], 'probabilities' => [0.1, 0.5, 0.4], 'confidence' => 0.5],
        ];

        yield 'keys, in the response order' => [
            new Score('How loud?', ['low' => 'Quiet', 'high' => 'Loud']),
            [1 => 0.75, 0 => 0.25],
            ['type' => 'score', 'score' => 0.75, 'legend' => ['Quiet', 'Loud'], 'probabilities' => [0.25, 0.75], 'confidence' => 0.75],
        ];
    }

    /**
     * @dataProvider provideAnswers
     */
    public function testAnswer(Score $question, array $probabilities, array $expected): void
    {
        $decision = new ScoreDecision($question, new ValueFormatter(Serializer::withJSONOptions()));

        $this->assertSame($expected, get_object_vars($decision->answer($probabilities)));
    }
}
