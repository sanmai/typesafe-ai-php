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

namespace TypeSafeAI;

use InvalidArgumentException;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use TypeSafeAI\DTO\Answer;
use TypeSafeAI\Question\Question;

use function count;
use function is_subclass_of;
use function sprintf;

/**
 * Parses the questions and answer types from the annotated constructor.
 *
 * We assume that
 * - each parameter needs exactly one question attribute
 * - the parameter name is the question id, and
 * - the parameter type is the expected type of the answer
 *
 * @template T of object
 * @final
 */
class AttributeReader
{
    /**
     * @param ReflectionClass<T> $reflection
     */
    public function __construct(private readonly ReflectionClass $reflection) {}

    /**
     * @return iterable<ReflectionParameter>
     */
    private function getParameters(): iterable
    {
        return $this->reflection->getConstructor()?->getParameters() ?? [];
    }

    /**
     * @return iterable<string, Question>
     */
    public function questions(): iterable
    {
        foreach ($this->getParameters() as $parameter) {
            yield $parameter->getName() => self::questionInstance($parameter);
        }
    }

    private static function questionInstance(ReflectionParameter $parameter): Question
    {
        /** @var array<ReflectionAttribute<Question>> $attributes */
        $attributes = $parameter->getAttributes(Question::class, ReflectionAttribute::IS_INSTANCEOF);

        if (count($attributes) !== 1) {
            throw new InvalidArgumentException(
                sprintf('Expected one question attribute on $%s, got %d', $parameter->getName(), count($attributes)),
            );
        }

        return $attributes[0]->newInstance();
    }

    /**
     * Builds the result class from the answers.
     *
     * @return T
     */
    public function hydrate(SystemOneResult $result): object
    {
        return $this->reflection->newInstance(...$this->argsFrom($result));
    }

    /**
     * @return iterable<Answer>
     */
    private function argsFrom(SystemOneResult $result): iterable
    {
        foreach ($this->getParameters() as $parameter) {
            yield $result->answer(
                $parameter->getName(),
                self::answerTypeName($parameter),
            );
        }
    }

    /**
     * @return class-string<Answer>
     */
    private static function answerTypeName(ReflectionParameter $parameter): string
    {
        $type = $parameter->getType();

        if (
            !$type instanceof ReflectionNamedType
            || !is_subclass_of($type->getName(), Answer::class)
        ) {
            throw new InvalidArgumentException(sprintf('Expected $%s to be typed as an %s', $parameter->getName(), Answer::class));
        }

        return $type->getName();
    }

}
