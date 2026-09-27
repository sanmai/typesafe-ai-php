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
use TypeSafeAI\OpenAICompat\Decision\NoulDecision;
use TypeSafeAI\OpenAICompat\ValueFormatter;
use TypeSafeAI\Question\Noul;
use TypeSafeAI\Question\NoulCriteria;

/**
 * @covers \TypeSafeAI\OpenAICompat\Decision\NoulDecision
 */
class NoulDecisionTest extends TestCase
{
    public static function providePrompts(): iterable
    {
        // The rendered example from the specification
        yield 'criteria' => [
            new Noul('Is the action permitted?', new NoulCriteria(true: 'Every condition holds', false: 'A condition is missing')),
            "Is the action permitted?\n\nOptions:\n- no: A condition is missing\n- yes: Every condition holds",
        ];

        yield 'no criteria' => [
            new Noul('Is it urgent?'),
            "Is it urgent?\n\nOptions:\n- no\n- yes",
        ];
    }

    /**
     * @dataProvider providePrompts
     */
    public function testPrompt(Noul $question, string $expected): void
    {
        $decision = new NoulDecision($question, new ValueFormatter(Serializer::withJSONOptions()));

        $this->assertSame(['no', 'yes'], $decision->labels());
        $this->assertSame($expected, $decision->prompt());
    }

    public function testAnswer(): void
    {
        $decision = new NoulDecision(new Noul('Is it urgent?'), new ValueFormatter(Serializer::withJSONOptions()));

        $this->assertSame(
            ['type' => 'noul', 'noul' => 0.9],
            get_object_vars($decision->answer(['no' => 0.1, 'yes' => 0.9])),
        );
    }
}
