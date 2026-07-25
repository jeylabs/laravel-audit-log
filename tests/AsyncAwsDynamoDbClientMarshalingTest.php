<?php

namespace Jeylabs\AuditLog\Test;

use Jeylabs\AuditLog\Stores\AsyncAwsDynamoDbClient;
use ReflectionMethod;

/**
 * Round-trips a representative item through marshal()/unmarshal() using
 * the real async-aws AttributeValue value object (no network calls
 * involved), to guard against attribute-value key mistakes such as
 * DynamoDB's uppercase NULL/BOOL keys.
 */
class AsyncAwsDynamoDbClientMarshalingTest extends TestCase
{
    public function test_marshal_and_unmarshal_round_trips_every_supported_type(): void
    {
        $client = new AsyncAwsDynamoDbClient(['region' => 'us-east-1']);

        $marshal = new ReflectionMethod($client, 'marshal');
        $unmarshal = new ReflectionMethod($client, 'unmarshal');

        $original = [
            'id' => 'abc-123',
            'description' => 'created',
            'is_deleted' => false,
            'nothing' => null,
            'subject_id' => 42,
            'score' => 1.5,
            'tags' => ['a', 'b'],
            'properties' => ['foo' => 'bar', 'nested' => ['x' => 1]],
        ];

        $wire = $marshal->invoke($client, $original);
        $roundTripped = $unmarshal->invoke($client, $wire);

        $this->assertSame($original, $roundTripped);
    }
}
