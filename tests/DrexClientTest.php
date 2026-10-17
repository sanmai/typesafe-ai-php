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

use GuzzleHttp\Client;
use InvalidArgumentException;

use function putenv;

use TypeSafeAI\DrexClient;
use TypeSafeAI\SystemOneRequest;

/**
 * @covers \TypeSafeAI\DrexClient
 * @covers \TypeSafeAI\SystemOneHttpClient
 * @covers \TypeSafeAI\RequestContext
 */
class DrexClientTest extends TestCase
{
    public function testCreateInstance(): void
    {
        putenv('DREX_BASE_URL');

        /** @var Client $httpClient */
        $httpClient = $this->getPropertyValue(DrexClient::createInstance('secret'), 'client');

        $this->assertSame('https://console.nace.ai', (string) $httpClient->getConfig('base_uri'));
    }

    public function testCreateInstanceReadsEnvironment(): void
    {
        putenv('DREX_API_KEY=from-env');
        putenv('DREX_BASE_URL=https://env.example.com');

        try {
            /** @var Client $httpClient */
            $httpClient = $this->getPropertyValue(DrexClient::createInstance(), 'client');

            $this->assertSame('https://env.example.com', (string) $httpClient->getConfig('base_uri'));
            $this->assertSame('Bearer from-env', $httpClient->getConfig('headers')['Authorization']);
        } finally {
            putenv('DREX_API_KEY');
            putenv('DREX_BASE_URL');
        }
    }

    public function testCreateInstanceWithoutApiKey(): void
    {
        putenv('DREX_API_KEY');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No API key given and DREX_API_KEY is not set.');

        DrexClient::createInstance();
    }

    public static function provideModels(): iterable
    {
        yield 'default model' => [
            SystemOneRequest::build('Help!'),
            '{"state":"Help!","questions":{}}',
        ];

        yield 'explicit model' => [
            SystemOneRequest::build('Help!', 'drex-v1.5'),
            '{"model":"drex-v1.5","state":"Help!","questions":{}}',
        ];
    }

    /**
     * @dataProvider provideModels
     */
    public function testSystemOne(SystemOneRequest $request, string $expected): void
    {
        $this->clientWith([self::success()], class: DrexClient::class)->systemOne($request);

        $this->assertSame('https://console.nace.ai/v1/systemone', (string) $this->getLastRequest()->getUri());
        $this->assertSame($expected, (string) $this->getLastRequest()->getBody());
    }
}
