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

use function array_keys;
use function array_map;
use function json_decode;
use function json_encode;

use PHPUnit\Framework\TestCase;
use TypeSafeAI\OpenAICompat\Distribution;
use UnexpectedValueException;

/**
 * @covers \TypeSafeAI\OpenAICompat\Distribution
 */
class DistributionTest extends TestCase
{
    public static function provideValid(): iterable
    {
        yield 'strict' => ['{"probabilities": {"yes": 0.9, "no": 0.1}}', ['no' => 0.1, 'yes' => 0.9]];

        yield 'integers' => ['{"probabilities": {"no": 0, "yes": 1}}', ['no' => 0.0, 'yes' => 1.0]];

        yield 'within strict tolerance' => ['{"probabilities": {"no": 0.2, "yes": 0.8009}}', ['no' => 0.2, 'yes' => 0.8009]];

        yield 'renormalized' => ['{"probabilities": {"no": 0.2, "yes": 0.81}}', ['no' => 0.2 / 1.01, 'yes' => 0.81 / 1.01]];

        yield 'renormalized down' => ['{"probabilities": {"no": 0.2, "yes": 0.79}}', ['no' => 0.2 / 0.99, 'yes' => 0.79 / 0.99]];
    }

    /**
     * @dataProvider provideValid
     */
    public function testValid(string $content, array $expected): void
    {
        $this->assertSame($expected, Distribution::of(json_decode($content, true), ['no', 'yes'])->probabilities);
    }

    public static function provideInvalid(): iterable
    {
        yield 'not an object' => ['[0.1, 0.9]', 'Expected an object with only "probabilities"'];
        yield 'extra top-level key' => ['{"probabilities": {"no": 0.1, "yes": 0.9}, "reason": "x"}', 'Expected an object with only "probabilities"'];
        yield 'wrong top-level key' => ['{"distribution": {"no": 0.1, "yes": 0.9}}', 'Expected an object with only "probabilities"'];
        yield 'probabilities not an object' => ['{"probabilities": 0.9}', 'Expected an object with only "probabilities"'];
        yield 'missing key' => ['{"probabilities": {"yes": 1}}', 'Expected 2 probabilities, got 1'];
        yield 'extra key' => ['{"probabilities": {"no": 0.1, "yes": 0.8, "maybe": 0.1}}', 'Expected 2 probabilities, got 3'];
        yield 'wrong key' => ['{"probabilities": {"no": 0.1, "Yes": 0.9}}', 'Expected a probability for "yes"'];
        yield 'boolean' => ['{"probabilities": {"no": false, "yes": true}}', 'Expected a probability for "no"'];
        yield 'string' => ['{"probabilities": {"no": "0.1", "yes": 0.9}}', 'Expected a probability for "no"'];
        yield 'negative' => ['{"probabilities": {"no": -0.1, "yes": 1.1}}', 'Expected a probability for "no"'];
        yield 'above one' => ['{"probabilities": {"no": 0, "yes": 1.01}}', 'Expected a probability for "yes"'];
        yield 'sum too high' => ['{"probabilities": {"no": 0.2, "yes": 0.83}}', 'Probabilities sum to 1.03'];
        yield 'sum too low' => ['{"probabilities": {"no": 0.2, "yes": 0.77}}', 'Probabilities sum to 0.97'];
    }

    /**
     * @dataProvider provideInvalid
     */
    public function testInvalid(string $content, string $message): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage($message);

        Distribution::of(json_decode($content, true), ['no', 'yes']);
    }

    public function testNoLabels(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Expected at least one option');

        Distribution::of(['probabilities' => []], []);
    }

    public static function provideArgmax(): iterable
    {
        yield 'highest' => ['{"probabilities": {"b": 0.2, "a": 0.1, "c": 0.7}}', 'c', 0.7];
        yield 'tie selects the smallest label' => ['{"probabilities": {"c": 0.4, "b": 0.4, "a": 0.2}}', 'b', 0.4];
        yield 'lexicographic, not numeric' => ['{"probabilities": {"2": 0.5, "10": 0.5}}', '10', 0.5];
        yield 'single option' => ['{"probabilities": {"only": 1}}', 'only', 1.0];
    }

    /**
     * @dataProvider provideArgmax
     */
    public function testArgmax(string $content, string $label, float $confidence): void
    {
        $object = json_decode($content, true);
        $distribution = Distribution::of($object, array_map(strval(...), array_keys($object['probabilities'])));

        $this->assertSame($label, $distribution->argmax());
        $this->assertSame($confidence, $distribution->confidence());
    }

    public static function provideSchema(): iterable
    {
        yield 'options' => [['no', 'yes'], '{"no":{"type":"number"},"yes":{"type":"number"}}'];

        // Level indices must be keys of an object, not a list
        yield 'level indices' => [['0', '1'], '{"0":{"type":"number"},"1":{"type":"number"}}'];
    }

    /**
     * @dataProvider provideSchema
     */
    public function testSchema(array $labels, string $properties): void
    {
        $probabilities = Distribution::schema($labels)['properties']['probabilities'];

        $this->assertSame($properties, json_encode($probabilities['properties']));
        $this->assertSame($labels, $probabilities['required']);
    }
}
