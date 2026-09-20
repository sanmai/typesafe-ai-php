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

use TypeSafeAI\SystemOneResult;

use function basename;
use function glob;
use function str_starts_with;

class SerializationTest extends TestCase
{
    private const PREFIX_CLASS_MAP = [
        'evaluation' => SystemOneResult::class,
    ];

    public static function provideFiles(): iterable
    {
        foreach (glob(__DIR__ . '/data/*.json') as $file) {
            $basename = basename($file);

            foreach (self::PREFIX_CLASS_MAP as $prefix => $className) {
                if (str_starts_with($basename, $prefix)) {
                    yield $basename => [$file, $className];
                    continue 2;
                }
            }

            yield $basename => [$file];
        }
    }

    /**
     * @dataProvider provideFiles
     */
    public function testDeserialize(string $file, ?string $className = null): void
    {
        if ($className === null) {
            $this->markTestIncomplete("No matching class for $file");
        }

        $response = $this->deserializeFile($file, $className);

        $this->assertDeserializedSame($file, $response);
    }
}
