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

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

use JSONSerializer\Serializer;
use PHPUnit\Framework\TestCase;
use Tests\TypeSafeAI\Doubles\ExampleState;
use TypeSafeAI\OpenAICompat\ValueFormatter;

/**
 * @covers \TypeSafeAI\OpenAICompat\ValueFormatter
 */
class ValueFormatterTest extends TestCase
{
    public static function provideValues(): iterable
    {
        yield 'text' => ['Which team?', 'Which team?'];

        yield 'empty text' => ['', ''];

        yield 'text that is JSON' => ['{"a":1}', '{"a":1}'];

        yield 'null' => [null, ''];

        yield 'list' => [['Calm', 'Angry'], '["Calm","Angry"]'];

        yield 'map' => [['what' => 'Charges', 'examples' => ['Refund?']], '{"what":"Charges","examples":["Refund?"]}'];

        yield 'nested null' => [['level' => null], '{"level":null}'];

        // Private properties and nulls, as TypeSafeClient sends them
        yield 'object' => [new ExampleState(), '{"id":42,"assignee":null,"secret":"private properties are sent too"}'];
    }

    /**
     * @dataProvider provideValues
     */
    public function testFormat(mixed $value, string $expected): void
    {
        $formatter = new ValueFormatter(Serializer::withJSONOptions());

        $this->assertSame($expected, $formatter->format($value));
    }

    public function testSerializerOptions(): void
    {
        $formatter = new ValueFormatter(Serializer::withJSONOptions(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->assertSame('{"what":"Счета / charges"}', $formatter->format(['what' => 'Счета / charges']));
    }
}
