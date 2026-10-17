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

use function json_encode;

use PHPUnit\Framework\TestCase;
use TypeSafeAI\OpenAICompat\Decision\Decision;
use TypeSafeAI\OpenAICompat\Schema;

/**
 * @covers \TypeSafeAI\OpenAICompat\Schema
 */
class SchemaTest extends TestCase
{
    public static function provideSchemas(): iterable
    {
        yield 'labels' => [
            ['is_urgent' => ['no', 'yes']],
            '{"type":"object","properties":{"is_urgent":{"type":"object","properties":{"no":{"type":"number"},"yes":{"type":"number"}},"required":["no","yes"],"additionalProperties":false}},"required":["is_urgent"],"additionalProperties":false}',
        ];

        // Level indices and numeric IDs are object keys and required strings, not a list
        yield 'numeric keys' => [
            [7 => ['0', '1']],
            '{"type":"object","properties":{"7":{"type":"object","properties":{"0":{"type":"number"},"1":{"type":"number"}},"required":["0","1"],"additionalProperties":false}},"required":["7"],"additionalProperties":false}',
        ];

        yield 'no questions' => [
            [],
            '{"type":"object","properties":{},"required":[],"additionalProperties":false}',
        ];
    }

    /**
     * @dataProvider provideSchemas
     * @param array<array-key, list<string>> $labels
     */
    public function testOf(array $labels, string $expected): void
    {
        $decisions = [];

        foreach ($labels as $id => $decisionLabels) {
            $decisions[$id] = $this->createConfiguredMock(Decision::class, ['labels' => $decisionLabels]);
        }

        $this->assertSame($expected, json_encode(Schema::of($decisions)));
    }
}
