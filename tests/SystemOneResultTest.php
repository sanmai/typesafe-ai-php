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

use TypeSafeAI\DTO\ChoiceAnswer;
use TypeSafeAI\DTO\NoulAnswer;
use TypeSafeAI\DTO\ScoreAnswer;
use TypeSafeAI\SystemOneResult;
use UnexpectedValueException;

/**
 * @covers \TypeSafeAI\SystemOneResult
 */
class SystemOneResultTest extends TestCase
{
    private SystemOneResult $response;

    protected function setUp(): void
    {
        parent::setUp();

        $this->response = $this->deserializeFile(__DIR__ . '/data/evaluation_mixed.json', SystemOneResult::class);
    }

    public function testFields(): void
    {
        $this->assertSame('jev-latest', $this->response->model);
        $this->assertSame(540, $this->response->usage->input_tokens);
        $this->assertSame(96, $this->response->usage->output_tokens);
        $this->assertCount(3, $this->response->answers);
    }

    public function testNoul(): void
    {
        $answer = $this->response->noul('is_urgent');

        $this->assertSame(0.92, $answer->noul);
    }

    public function testChoice(): void
    {
        $answer = $this->response->choice('department');

        $this->assertSame('technical', $answer->choice);
        $this->assertSame(['billing' => 0.08, 'technical' => 0.85, 'sales' => 0.07], $answer->probabilities);
        $this->assertSame(0.82, $answer->confidence);
    }

    public function testScore(): void
    {
        $answer = $this->response->score('frustration');

        $this->assertSame(1.6, $answer->score);
        $this->assertSame(['Calm', 'Frustrated', 'Very angry'], $answer->legend);
        $this->assertSame([0.05, 0.3, 0.65], $answer->probabilities);
        $this->assertSame(0.78, $answer->confidence);
    }

    public static function provideWrongIds(): iterable
    {
        yield 'wrong type' => ['noul', 'department', 'Expected ' . NoulAnswer::class . ' for "department", got ' . ChoiceAnswer::class];
        yield 'missing id' => ['score', 'nope', 'Expected ' . ScoreAnswer::class . ' for "nope", got null'];
        yield 'choice' => ['choice', 'frustration', 'Expected ' . ChoiceAnswer::class . ' for "frustration", got ' . ScoreAnswer::class];
    }

    /**
     * @dataProvider provideWrongIds
     */
    public function testWrongId(string $method, string $id, string $message): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage($message);

        $this->response->{$method}($id);
    }
}
