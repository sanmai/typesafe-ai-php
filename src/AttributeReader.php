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
use UnexpectedValueException;

use function count;
use function is_subclass_of;
use function sprintf;

/**
 * The questions that a result class asks, read from the attributes on its constructor.
 *
 * Each parameter needs one question attribute. The parameter name is the question id, and the
 * parameter type is the answer that comes back under it.
 *
 * @template T of object
 */
class AttributeReader
{
    /**
     * @var array<string, Question>
     */
    public array $questions = [];

    /**
     * @var array<string, class-string<Answer>>
     */
    private array $answers = [];

    /**
     * @param class-string<T> $class
     * @param null|ReflectionClass<T> $reflection
     * @throws InvalidArgumentException When a parameter lacks expected attributes, or does not assume an answer.
     */
    public function __construct(private readonly string $class, ?ReflectionClass $reflection = null)
    {

        $reflection ??= new ReflectionClass($class);

        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
            $this->questions[$parameter->getName()] = self::question($parameter);
            $this->answers[$parameter->getName()] = self::answerType($parameter);
        }
    }

    /**
     * Builds the result class from the answers, each under the id of its parameter.
     *
     * @return T
     * @throws UnexpectedValueException When an answer is missing, or has another type.
     */
    public function hydrate(SystemOneResult $result): object
    {
        $arguments = [];

        foreach ($this->answers as $id => $type) {
            $arguments[$id] = $result->answer($id, $type);
        }

        return new $this->class(...$arguments);
    }

    private static function question(ReflectionParameter $parameter): Question
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
     * @return class-string<Answer>
     */
    private static function answerType(ReflectionParameter $parameter): string
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
