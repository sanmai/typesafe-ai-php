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

namespace Tests\TypeSafeAI;

use InvalidArgumentException;
use Tests\TypeSafeAI\Doubles\DoubleQuestionAttribute;
use Tests\TypeSafeAI\Doubles\MissingQuestionAttribute;
use Tests\TypeSafeAI\Doubles\NoQuestions;
use Tests\TypeSafeAI\Doubles\TicketDecision;
use Tests\TypeSafeAI\Doubles\UntypedAnswer;
use Tests\TypeSafeAI\Doubles\WrongAnswerType;
use TypeSafeAI\DTO\Answer;
use TypeSafeAI\Question\Choice;
use TypeSafeAI\Question\Noul;
use TypeSafeAI\Question\Score;
use TypeSafeAI\AttributeReader;
use TypeSafeAI\SystemOneResult;

use function array_keys;

/**
 * @covers \TypeSafeAI\AttributeReader
 */
class AttributeReaderTest extends TestCase
{
    public function testQuestions(): void
    {
        $questions = (new AttributeReader(TicketDecision::class))->questions;

        $this->assertSame(['is_urgent', 'department', 'frustration'], array_keys($questions));
        $this->assertInstanceOf(Noul::class, $questions['is_urgent']);
        $this->assertInstanceOf(Choice::class, $questions['department']);
        $this->assertInstanceOf(Score::class, $questions['frustration']);
    }

    public function testQuestionArguments(): void
    {
        $question = (new AttributeReader(TicketDecision::class))->questions['is_urgent'];

        $this->assertSame('Does this convey urgency?', $question->instructions);
        $this->assertSame('Explicitly time-sensitive', $question->criteria->true);
    }

    public function testNoConstructor(): void
    {
        $this->assertSame([], (new AttributeReader(NoQuestions::class))->questions);
    }

    public function testHydrate(): void
    {
        $result = $this->deserializeFile(__DIR__ . '/data/evaluation_mixed.json', SystemOneResult::class);

        $decision = (new AttributeReader(TicketDecision::class))->hydrate($result);

        $this->assertSame(0.92, $decision->is_urgent->noul);
        $this->assertSame('technical', $decision->department->choice);
        $this->assertSame(1.6, $decision->frustration->score);
    }

    public static function provideInvalidClasses(): iterable
    {
        yield 'no attribute' => [MissingQuestionAttribute::class, 'Expected one question attribute on $is_urgent, got 0'];
        yield 'two attributes' => [DoubleQuestionAttribute::class, 'Expected one question attribute on $is_urgent, got 2'];
        yield 'not an answer' => [WrongAnswerType::class, 'Expected $department to be typed as an ' . Answer::class];
        yield 'no type' => [UntypedAnswer::class, 'Expected $frustration to be typed as an ' . Answer::class];
    }

    /**
     * @param class-string $class
     * @dataProvider provideInvalidClasses
     */
    public function testInvalidClass(string $class, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        new AttributeReader($class);
    }
}
