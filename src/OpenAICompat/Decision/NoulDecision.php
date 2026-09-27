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

namespace TypeSafeAI\OpenAICompat\Decision;

use TypeSafeAI\DTO\NoulAnswer;
use TypeSafeAI\OpenAICompat\Distribution;
use TypeSafeAI\OpenAICompat\Text;
use TypeSafeAI\Question\Choice;
use TypeSafeAI\Question\Noul;

/**
 * Presents a yes/no question as a choice between "no" and "yes".
 *
 * @final
 */
class NoulDecision implements Decision
{
    private readonly ChoiceDecision $choice;

    public function __construct(Noul $question, Text $text)
    {
        $this->choice = new ChoiceDecision(new Choice($question->instructions, [
            'no' => $question->criteria->false,
            'yes' => $question->criteria->true,
        ]), $text);
    }

    public function labels(): array
    {
        return $this->choice->labels();
    }

    public function prompt(): string
    {
        return $this->choice->prompt();
    }

    public function answer(Distribution $distribution): NoulAnswer
    {
        $answer = new NoulAnswer();
        $answer->noul = $distribution->probabilities['yes'];

        return $answer;
    }
}
