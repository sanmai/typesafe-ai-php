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

namespace TypeSafeAI\OpenAICompat;

use function abs;
use function array_fill_keys;
use function array_keys;
use function array_search;
use function array_sum;
use function count;
use function is_array;
use function is_float;
use function is_int;
use function ksort;
use function max;

use const SORT_STRING;

use function sprintf;

use UnexpectedValueException;

/**
 * A probability distribution over a label set, as verbalized by a model.
 *
 * @final
 */
class Distribution
{
    private const STRICT_TOLERANCE = 0.001;

    private const TOLERANCE = 0.02;

    private const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'probabilities' => [
                'type' => 'object',
                'additionalProperties' => false,
            ],
        ],
        'required' => ['probabilities'],
        'additionalProperties' => false,
    ];

    /**
     * @param non-empty-array<array-key, float> $probabilities Each label mapped to its probability, in label order.
     */
    private function __construct(public readonly array $probabilities) {}

    /**
     * Returns the JSON schema of a {"probabilities": {...}} object over the labels.
     *
     * @param list<string> $labels
     * @return array<string, mixed>
     */
    public static function schema(array $labels): array
    {
        $schema = self::SCHEMA;
        // An object also for the level indices of a score
        $schema['properties']['probabilities']['properties'] = (object) array_fill_keys($labels, ['type' => 'number']);
        $schema['properties']['probabilities']['required'] = $labels;

        return $schema;
    }

    /**
     * Validates a decoded {"probabilities": {...}} object against the labels.
     *
     * A total within 0.001 of 1 is used unchanged, a total within 0.02 is renormalized, anything else is rejected.
     *
     * @param array<mixed> $object
     * @param list<string> $labels
     * @throws UnexpectedValueException When the distribution is invalid
     */
    public static function of(array $object, array $labels): self
    {
        if (['probabilities'] !== array_keys($object) || !is_array($object['probabilities'])) {
            throw new UnexpectedValueException('Expected an object with only "probabilities"');
        }

        return self::validate($object['probabilities'], $labels);
    }

    /**
     * @param array<mixed> $probabilities
     * @param list<string> $labels
     */
    private static function validate(array $probabilities, array $labels): self
    {
        if ([] === $labels) {
            throw new UnexpectedValueException('Expected at least one option');
        }

        if (count($probabilities) !== count($labels)) {
            throw new UnexpectedValueException(sprintf('Expected %d probabilities, got %d', count($labels), count($probabilities)));
        }

        $values = [];

        foreach ($labels as $label) {
            $value = $probabilities[$label] ?? null;

            if ((!is_int($value) && !is_float($value)) || $value < 0 || $value > 1) {
                throw new UnexpectedValueException(sprintf('Expected a probability for "%s"', $label));
            }

            $values[$label] = (float) $value;
        }

        $deviation = abs(array_sum($values) - 1);

        if ($deviation > self::TOLERANCE) {
            throw new UnexpectedValueException(sprintf('Probabilities sum to %s', array_sum($values)));
        }

        if ($deviation > self::STRICT_TOLERANCE) {
            return new self(self::renormalize($values));
        }

        return new self($values);
    }

    /**
     * @param non-empty-array<array-key, float> $values
     * @return non-empty-array<array-key, float>
     */
    private static function renormalize(array $values): array
    {
        $total = array_sum($values);

        foreach ($values as $label => $value) {
            $values[$label] = $value / $total;
        }

        return $values;
    }

    /**
     * Returns the most probable label. A tie selects the lexicographically smallest label.
     */
    public function argmax(): string
    {
        $probabilities = $this->probabilities;
        ksort($probabilities, SORT_STRING);

        return (string) array_search($this->confidence(), $probabilities, true);
    }

    public function confidence(): float
    {
        return max($this->probabilities);
    }
}
