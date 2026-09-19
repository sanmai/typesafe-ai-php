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

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use TypeSafeAI\TypeSafeClient;
use JMS\Serializer\SerializerInterface;
use JSONSerializer;
use ReflectionObject;

use function array_walk;
use function dirname;
use function end;
use function file_get_contents;
use function get_class;
use function is_array;
use function json_decode;
use function json_encode;
use function ksort;
use function sprintf;
use function str_replace;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_UNICODE;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected SerializerInterface $serializer;
    protected array $requests = [];
    protected MockHandler $mock;

    protected function setUp(): void
    {
        $this->serializer = JSONSerializer\Serializer::withJSONOptions(JSON_PRETTY_PRINT);
        $this->requests = [];
    }

    protected static function krsort(&$array)
    {
        if (!is_array($array)) {
            return;
        }

        array_walk($array, fn(&$array) => self::krsort($array));
        ksort($array);
    }

    /**
     * @param object $response
     */
    protected function assertDeserializedSame(string $file, $response): void
    {
        $expected = json_decode(file_get_contents($file), true);
        self::krsort($expected);

        $actual = json_decode($this->serializer->serialize($response, 'json'), true);
        self::krsort($actual);

        $this->assertSame(
            json_encode($expected, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            sprintf(
                "Failed to deserialize %s to %s",
                str_replace(dirname(__DIR__), '.', $file),
                get_class($response),
            ),
        );
    }

    /**
     * @template T of object
     * @param class-string<T> $type
     * @return T
     */
    protected function deserializeFile(string $file, string $type)
    {
        $this->assertFileExists($file);

        return $this->serializer->deserialize(file_get_contents($file), $type, 'json');
    }

    protected function getPropertyValue(object $object, string $propertyName)
    {
        $property = (new ReflectionObject($object))->getProperty($propertyName);
        $property->setAccessible(true);

        return $property->getValue($object);
    }

    protected function clientWith(array $responses, array $clientOptions = []): TypeSafeClient
    {
        $client = TypeSafeClient::createInstance(
            'secret',
            ['X-Foo' => 'bar'],
            ['default_retry_multiplier' => 0],
            $clientOptions,
        );

        $this->mock = new MockHandler($responses);

        /** @var HandlerStack $stack */
        $stack = $this->getPropertyValue($client, 'stack');
        $stack->setHandler($this->mock);
        $stack->push(Middleware::history($this->requests));

        return $client;
    }

    protected static function success(): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], file_get_contents(__DIR__ . '/data/evaluation_noul.json'));
    }

    protected function getLastRequest(): Request
    {
        return end($this->requests)['request'];
    }

    protected function getLastOptions(): array
    {
        return end($this->requests)['options'];
    }
}
