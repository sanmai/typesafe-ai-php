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

namespace Tests\TypeSafeAI\OpenAICompat\DTO;

use JSONSerializer\Serializer;
use PHPUnit\Framework\TestCase;
use TypeSafeAI\OpenAICompat\DTO\Distributions;

/**
 * @covers \TypeSafeAI\OpenAICompat\DTO\Distributions
 */
class DistributionsTest extends TestCase
{
    public static function provideDistributions(): iterable
    {
        yield 'labels' => [
            '{"is_urgent": {"no": 0.1, "yes": 0.9}, "department": {"billing": 0.8, "technical": 0.2}}',
            [
                'is_urgent' => ['no' => 0.1, 'yes' => 0.9],
                'department' => ['billing' => 0.8, 'technical' => 0.2],
            ],
        ];

        yield 'integers as floats' => [
            '{"is_urgent": {"no": 0, "yes": 1}}',
            ['is_urgent' => ['no' => 0.0, 'yes' => 1.0]],
        ];

        yield 'level indices' => [
            '{"frustration": {"0": 0.25, "1": 0.75}}',
            ['frustration' => [0 => 0.25, 1 => 0.75]],
        ];

        yield 'numeric question ID' => [
            '{"7": {"no": 0.5, "yes": 0.5}}',
            [7 => ['no' => 0.5, 'yes' => 0.5]],
        ];

        yield 'empty' => [
            '{}',
            [],
        ];
    }

    /**
     * @dataProvider provideDistributions
     */
    public function testDeserialize(string $json, array $expected): void
    {
        $distributions = Serializer::withJSONOptions()->deserializeJson($json, Distributions::class);

        $this->assertInstanceOf(Distributions::class, $distributions);

        foreach ($expected as $id => $probabilities) {
            $this->assertSame($probabilities, $distributions->probabilities($id));
        }
    }

    public function testProbabilities(): void
    {
        $distributions = Distributions::withMap(['is_urgent' => ['no' => 0.1, 'yes' => 0.9], 7 => ['no' => 0.5, 'yes' => 0.5]]);

        $this->assertSame(['no' => 0.1, 'yes' => 0.9], $distributions->probabilities('is_urgent'));
        $this->assertSame(['no' => 0.5, 'yes' => 0.5], $distributions->probabilities(7));
    }
}
