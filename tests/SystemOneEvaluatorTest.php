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

use Tests\TypeSafeAI\Doubles\TicketDecision;
use TypeSafeAI\SystemOneEvaluator;
use TypeSafeAI\SystemOneRequest;
use TypeSafeAI\SystemOneResult;

/**
 * @covers \TypeSafeAI\SystemOneEvaluator
 */
class SystemOneEvaluatorTest extends TestCase
{
    public static function provideEvaluatedModels(): iterable
    {
        yield 'default model' => [[], SystemOneRequest::MODEL_LATEST];

        yield 'explicit model' => [['model' => 'jev-preview'], 'jev-preview'];
    }

    /**
     * @dataProvider provideEvaluatedModels
     * @param array<string, string> $arguments
     */
    public function testEvaluate(array $arguments, string $expectedModel): void
    {
        $state = ['message' => 'Help! My payouts have been failing for 3 days.'];
        $response = $this->deserializeFile(__DIR__ . '/data/evaluation_mixed.json', SystemOneResult::class);

        $expected = SystemOneRequest::build($state, $expectedModel)
            ->noul('is_urgent', 'Does this convey urgency?', 'Explicitly time-sensitive', 'No urgency expressed')
            ->choice('department', 'Which team should handle this?', [
                'billing' => 'Payments, invoicing, refunds',
                'technical' => 'Bugs, outages, integrations',
                'sales' => null,
            ])
            ->score('frustration', 'How frustrated is the customer?', ['Calm', 'Frustrated', 'Very angry']);

        $evaluator = $this->getMockForTrait(SystemOneEvaluator::class);
        $evaluator->expects($this->once())
            ->method('systemOne')
            ->with($this->equalTo($expected))
            ->willReturn($response);

        $decision = $evaluator->evaluate($state, TicketDecision::class, ...$arguments);

        $this->assertInstanceOf(TicketDecision::class, $decision);
        $this->assertSame(0.92, $decision->is_urgent->noul);
        $this->assertSame('technical', $decision->department->choice);
        $this->assertSame(1.6, $decision->frustration->score);
    }
}
