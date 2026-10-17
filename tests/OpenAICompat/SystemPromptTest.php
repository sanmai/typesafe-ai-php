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

use PHPUnit\Framework\TestCase;
use TypeSafeAI\OpenAICompat\Decision\Decision;
use TypeSafeAI\OpenAICompat\SystemPrompt;

/**
 * @covers \TypeSafeAI\OpenAICompat\SystemPrompt
 */
class SystemPromptTest extends TestCase
{
    public static function providePrompts(): iterable
    {
        yield 'questions in request order' => [
            ['is_urgent' => 'Urgent?', 'department' => 'Which team?'],
            SystemPrompt::PREAMBLE . "\n\n# Question is_urgent\n\nUrgent?\n\n# Question department\n\nWhich team?",
        ];

        yield 'numeric ID' => [
            [7 => 'Urgent?'],
            SystemPrompt::PREAMBLE . "\n\n# Question 7\n\nUrgent?",
        ];

        yield 'no questions' => [
            [],
            SystemPrompt::PREAMBLE,
        ];
    }

    /**
     * @dataProvider providePrompts
     * @param array<array-key, string> $prompts
     */
    public function testOf(array $prompts, string $expected): void
    {
        $decisions = [];

        foreach ($prompts as $id => $prompt) {
            $decisions[$id] = $this->createConfiguredMock(Decision::class, ['prompt' => $prompt]);
        }

        $this->assertSame($expected, SystemPrompt::of($decisions));
    }
}
