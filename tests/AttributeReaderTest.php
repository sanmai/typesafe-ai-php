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
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Tests\TypeSafeAI\Doubles\DoubleQuestionAttribute;
use Tests\TypeSafeAI\Doubles\MissingQuestionAttribute;
use Tests\TypeSafeAI\Doubles\NoQuestions;
use Tests\TypeSafeAI\Doubles\TicketDecision;
use Tests\TypeSafeAI\Doubles\UntypedAnswer;
use Tests\TypeSafeAI\Doubles\WrongAnswerType;
use TypeSafeAI\DTO\Answer;
use TypeSafeAI\DTO\NoulAnswer;
use TypeSafeAI\Question\Choice;
use TypeSafeAI\Question\Noul;
use TypeSafeAI\Question\Score;
use TypeSafeAI\AttributeReader;
use TypeSafeAI\SystemOneResult;

use function array_keys;
use function iterator_count;
use function iterator_to_array;
use function sprintf;

/**
 * @covers \TypeSafeAI\AttributeReader
 */
class AttributeReaderTest extends TestCase
{
    public function testConstructorReadsNothing(): void
    {
        $reflection = $this->createMock(ReflectionClass::class);
        $reflection->expects($this->never())->method($this->anything());

        new AttributeReader($reflection);
    }

    public function testQuestionsDoNotReadTheAnswerType(): void
    {
        $attribute = $this->createMock(ReflectionAttribute::class);
        $attribute->method('newInstance')->willReturn(new Noul());

        $parameter = $this->createMock(ReflectionParameter::class);
        $parameter->method('getName')->willReturn('is_urgent');
        $parameter->method('getAttributes')->willReturn([$attribute]);
        $parameter->expects($this->never())->method('getType');

        $reader = new AttributeReader($this->constructorOf($parameter));

        $this->assertSame(['is_urgent'], array_keys(iterator_to_array($reader)));
    }

    public function testHydrationDoesNotReadTheQuestions(): void
    {
        $type = $this->createMock(ReflectionNamedType::class);
        $type->method('getName')->willReturn(NoulAnswer::class);

        $parameter = $this->createMock(ReflectionParameter::class);
        $parameter->method('getName')->willReturn('is_urgent');
        $parameter->method('getType')->willReturn($type);
        $parameter->expects($this->never())->method('getAttributes');

        $reflection = $this->constructorOf($parameter);
        $reflection->expects($this->once())
            ->method('newInstance')
            ->with($this->isInstanceOf(NoulAnswer::class))
            ->willReturn(new NoQuestions());

        $result = $this->deserializeFile(__DIR__ . '/data/evaluation_mixed.json', SystemOneResult::class);

        $this->assertInstanceOf(NoQuestions::class, (new AttributeReader($reflection))->hydrate($result));
    }

    /**
     * @return ReflectionClass<object>&MockObject
     */
    private function constructorOf(ReflectionParameter $parameter): ReflectionClass
    {
        $constructor = $this->createMock(ReflectionMethod::class);
        $constructor->method('getParameters')->willReturn([$parameter]);

        $reflection = $this->createMock(ReflectionClass::class);
        $reflection->method('getConstructor')->willReturn($constructor);

        return $reflection;
    }

    public function testQuestions(): void
    {
        $questions = iterator_to_array(new AttributeReader(new ReflectionClass(TicketDecision::class)));

        $this->assertSame(['is_urgent', 'department', 'frustration'], array_keys($questions));
        $this->assertInstanceOf(Noul::class, $questions['is_urgent']);
        $this->assertInstanceOf(Choice::class, $questions['department']);
        $this->assertInstanceOf(Score::class, $questions['frustration']);
    }

    public function testQuestionArguments(): void
    {
        $question = iterator_to_array(new AttributeReader(new ReflectionClass(TicketDecision::class)))['is_urgent'];

        $this->assertSame('Does this convey urgency?', $question->instructions);
        $this->assertSame('Explicitly time-sensitive', $question->criteria->true);
    }

    public function testNoConstructor(): void
    {
        $reader = new AttributeReader(new ReflectionClass(NoQuestions::class));

        $this->assertInstanceOf(NoQuestions::class, $reader->hydrate(new SystemOneResult()));
        $this->assertSame([], iterator_to_array($reader));
    }

    public function testHydrate(): void
    {
        $result = $this->deserializeFile(__DIR__ . '/data/evaluation_mixed.json', SystemOneResult::class);

        $decision = (new AttributeReader(new ReflectionClass(TicketDecision::class)))->hydrate($result);

        $this->assertSame(0.92, $decision->is_urgent->noul);
        $this->assertSame('technical', $decision->department->choice);
        $this->assertSame(1.6, $decision->frustration->score);
    }

    public static function provideInvalidClasses(): iterable
    {
        yield 'no attribute' => [MissingQuestionAttribute::class, 'Expected one question attribute on $is_urgent, got 0'];
        yield 'two attributes' => [DoubleQuestionAttribute::class, 'Expected one question attribute on $is_urgent, got 2'];
        yield 'not an answer' => [WrongAnswerType::class, sprintf('Expected $department to be typed as an %s', Answer::class)];
        yield 'no type' => [UntypedAnswer::class, sprintf('Expected $frustration to be typed as an %s', Answer::class)];
    }

    /**
     * @param class-string $class
     * @dataProvider provideInvalidClasses
     */
    public function testInvalidClass(string $class, string $message): void
    {
        $reader = new AttributeReader(new ReflectionClass($class));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        iterator_count($reader);
        $reader->hydrate(new SystemOneResult());
    }
}
