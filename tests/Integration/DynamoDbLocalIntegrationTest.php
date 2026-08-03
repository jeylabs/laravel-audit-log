<?php

namespace Jeylabs\AuditLog\Test\Integration;

use AsyncAws\Core\Configuration;
use AsyncAws\DynamoDb\DynamoDbClient as AsyncAwsClient;
use AsyncAws\DynamoDb\Enum\BillingMode;
use AsyncAws\DynamoDb\Enum\KeyType;
use AsyncAws\DynamoDb\Enum\ProjectionType;
use AsyncAws\DynamoDb\Enum\ScalarAttributeType;
use AsyncAws\DynamoDb\Exception\ResourceNotFoundException;
use Illuminate\Support\Carbon;
use Jeylabs\AuditLog\Stores\AsyncAwsDynamoDbClient;
use Jeylabs\AuditLog\Stores\DynamoAuditLogStore;
use Jeylabs\AuditLog\Stores\DynamoItemMapper;
use Jeylabs\AuditLog\Test\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Round-trips DynamoAuditLogStore through the real async-aws wire
 * protocol against a live DynamoDB Local instance, rather than a mock of
 * our own DynamoDbClient interface. This is the only test in the suite
 * that talks to a real (local) Dynamo endpoint, so it's opt-in: it skips
 * itself unless DYNAMODB_LOCAL_ENDPOINT is set, e.g.
 *
 *   java -jar DynamoDBLocal.jar -inMemory -port 8000 -sharedDb
 *   DYNAMODB_LOCAL_ENDPOINT=http://127.0.0.1:8000 vendor/bin/phpunit --group dynamodb-local
 */
class DynamoDbLocalIntegrationTest extends TestCase
{
    protected string $table = 'audit_logs_it';

    protected ?AsyncAwsClient $rawClient = null;

    protected DynamoAuditLogStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        $endpoint = getenv('DYNAMODB_LOCAL_ENDPOINT');

        if (!$endpoint) {
            $this->markTestSkipped('Set DYNAMODB_LOCAL_ENDPOINT to run against a running DynamoDB Local instance.');
        }

        $config = [
            'endpoint' => $endpoint,
            'region' => 'us-east-1',
            'accessKeyId' => 'local',
            'accessKeySecret' => 'local',
        ];

        $this->rawClient = new AsyncAwsClient(Configuration::create($config));

        $this->createTable();

        $store = new AsyncAwsDynamoDbClient($config);

        $this->store = new DynamoAuditLogStore(
            $store,
            new DynamoItemMapper(),
            $this->table,
            'causer_index',
            'subject_index',
        );
    }

    protected function tearDown(): void
    {
        if ($this->rawClient) {
            try {
                $this->rawClient->deleteTable(['TableName' => $this->table]);
            } catch (ResourceNotFoundException) {
                // already gone
            }
        }

        parent::tearDown();
    }

    #[Group('dynamodb-local')]
    public function test_create_and_find_round_trip_through_the_real_wire_protocol(): void
    {
        $entry = $this->store->create([
            'log_name' => 'default',
            'description' => 'created',
            'subject_type' => 'App\\Models\\Post',
            'subject_id' => 7,
            'causer_type' => 'App\\Models\\User',
            'causer_id' => 42,
            'properties' => collect(['ip' => '127.0.0.1']),
        ]);

        $this->assertNotEmpty($entry->id);
        $this->assertSame('created', $entry->description);

        $fetched = $this->store->find($entry->id);

        $this->assertNotNull($fetched);
        $this->assertSame('created', $fetched->description);
        $this->assertSame('127.0.0.1', $fetched->getExtraProperty('ip'));
    }

    #[Group('dynamodb-local')]
    public function test_find_returns_null_for_a_missing_id(): void
    {
        $this->assertNull($this->store->find('does-not-exist'));
    }

    #[Group('dynamodb-local')]
    public function test_update_location_persists_through_a_real_get_and_put(): void
    {
        $entry = $this->store->create(['description' => 'visited']);

        $updated = $this->store->updateLocation($entry->id, '1.234', '5.678');
        $this->assertTrue($updated);

        $fetched = $this->store->find($entry->id);
        $this->assertSame('1.234', $fetched->latitude);
        $this->assertSame('5.678', $fetched->longitude);

        $this->assertFalse($this->store->updateLocation('does-not-exist', '1', '2'));
    }

    #[Group('dynamodb-local')]
    public function test_caused_by_queries_the_causer_gsi(): void
    {
        $this->store->create(['description' => 'a', 'causer_type' => 'App\\Models\\User', 'causer_id' => 42]);
        $this->store->create(['description' => 'b', 'causer_type' => 'App\\Models\\User', 'causer_id' => 99]);

        $results = $this->store->causedBy('App\\Models\\User', 42);

        $this->assertCount(1, $results);
        $this->assertSame('a', $results->first()->description);
    }

    #[Group('dynamodb-local')]
    public function test_for_subject_queries_the_subject_gsi(): void
    {
        $this->store->create(['description' => 'a', 'subject_type' => 'App\\Models\\Post', 'subject_id' => 7]);
        $this->store->create(['description' => 'b', 'subject_type' => 'App\\Models\\Post', 'subject_id' => 8]);

        $results = $this->store->forSubject('App\\Models\\Post', 7);

        $this->assertCount(1, $results);
        $this->assertSame('a', $results->first()->description);
    }

    #[Group('dynamodb-local')]
    public function test_delete_older_than_scans_and_deletes_real_items(): void
    {
        $old = $this->store->create(['description' => 'old', 'created_at' => '2020-01-01T00:00:00+00:00']);
        $new = $this->store->create(['description' => 'new']);

        $deleted = $this->store->deleteOlderThan(Carbon::now()->subDay());

        $this->assertSame(1, $deleted);
        $this->assertNull($this->store->find($old->id));
        $this->assertNotNull($this->store->find($new->id));
    }

    protected function createTable(): void
    {
        try {
            $this->rawClient->deleteTable(['TableName' => $this->table])->resolve();
        } catch (ResourceNotFoundException) {
            // fine, nothing to clean up
        }

        $this->rawClient->createTable([
            'TableName' => $this->table,
            'AttributeDefinitions' => [
                ['AttributeName' => 'id', 'AttributeType' => ScalarAttributeType::S],
                ['AttributeName' => 'causer_key', 'AttributeType' => ScalarAttributeType::S],
                ['AttributeName' => 'subject_key', 'AttributeType' => ScalarAttributeType::S],
            ],
            'KeySchema' => [
                ['AttributeName' => 'id', 'KeyType' => KeyType::HASH],
            ],
            'GlobalSecondaryIndexes' => [
                [
                    'IndexName' => 'causer_index',
                    'KeySchema' => [
                        ['AttributeName' => 'causer_key', 'KeyType' => KeyType::HASH],
                    ],
                    'Projection' => ['ProjectionType' => ProjectionType::ALL],
                ],
                [
                    'IndexName' => 'subject_index',
                    'KeySchema' => [
                        ['AttributeName' => 'subject_key', 'KeyType' => KeyType::HASH],
                    ],
                    'Projection' => ['ProjectionType' => ProjectionType::ALL],
                ],
            ],
            'BillingMode' => BillingMode::PAY_PER_REQUEST,
        ])->resolve();
    }
}
