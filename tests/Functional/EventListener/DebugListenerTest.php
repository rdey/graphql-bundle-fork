<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Functional\EventListener;

use GraphQL\Type\Introspection;
use Redeye\GraphQLBundle\Tests\Functional\TestCase;

class DebugListenerTest extends TestCase
{
    public function testDisabledDebugInfo(): void
    {
        $client = static::createClient(['test_case' => 'connection']);
        $response = $this->sendRequest($client, Introspection::getIntrospectionQuery(), true);
        $this->assertArrayNotHasKey('extensions', $response);
    }

    public function testEnabledDebugInfo(): void
    {
        $client = static::createClient(['test_case' => 'debug']);
        $response = $this->sendRequest($client, Introspection::getIntrospectionQuery(), true);
        $this->assertArrayHasKey('extensions', $response);
        $this->assertArrayHasKey('debug', $response['extensions']);
        $this->assertArrayHasKey('executionTime', $response['extensions']['debug']);
        $this->assertArrayHasKey('memoryUsage', $response['extensions']['debug']);
    }
}
