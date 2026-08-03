<?php

namespace Jeylabs\AuditLog\Test\Integration;

use Illuminate\Support\Carbon;
use Jeylabs\AuditLog\Models\MongoAuditLog;
use Jeylabs\AuditLog\Stores\EloquentAuditLogStore;
use Jeylabs\AuditLog\Test\TestCase;
use MongoDB\Laravel\MongoDBServiceProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Round-trips the mongodb driver (EloquentAuditLogStore + MongoAuditLog)
 * against a live MongoDB instance over the real mongodb/laravel-mongodb
 * + ext-mongodb stack, rather than sqlite standing in for it. This is
 * opt-in: it skips itself unless MONGODB_LOCAL_URI is set, e.g.
 *
 *   mongod --dbpath /tmp/mongo-data --port 27017 --bind_ip 127.0.0.1
 *   MONGODB_LOCAL_URI=mongodb://127.0.0.1:27017 vendor/bin/phpunit --group mongodb-local
 */
class MongoDbLocalIntegrationTest extends TestCase
{
    protected EloquentAuditLogStore $store;

    protected function getPackageProviders($app): array
    {
        return array_merge(parent::getPackageProviders($app), [MongoDBServiceProvider::class]);
    }

    protected function setUp(): void
    {
        $uri = getenv('MONGODB_LOCAL_URI');

        if (!$uri) {
            $this->markTestSkipped('Set MONGODB_LOCAL_URI to run against a running MongoDB instance.');
        }

        if (!extension_loaded('mongodb')) {
            $this->markTestSkipped('The mongodb PHP extension is not installed.');
        }

        parent::setUp();

        config([
            'database.connections.mongodb' => [
                'driver' => 'mongodb',
                'dsn' => $uri,
                'database' => 'audit_log_it',
            ],
        ]);

        MongoAuditLog::query()->getConnection()->getMongoDB()->drop();

        $this->store = new EloquentAuditLogStore(MongoAuditLog::class);
    }

    protected function tearDown(): void
    {
        if (getenv('MONGODB_LOCAL_URI') && extension_loaded('mongodb')) {
            MongoAuditLog::query()->getConnection()->getMongoDB()->drop();
        }

        parent::tearDown();
    }

    #[Group('mongodb-local')]
    public function test_create_persists_and_returns_a_model(): void
    {
        $model = $this->store->create([
            'log_name' => 'default',
            'description' => 'created',
            'subject_type' => 'App\\Models\\Post',
            'subject_id' => 1,
            'causer_type' => 'App\\Models\\User',
            'causer_id' => 42,
            'properties' => collect(['foo' => 'bar']),
        ]);

        $this->assertInstanceOf(MongoAuditLog::class, $model);
        $this->assertTrue($model->exists);
        $this->assertSame(1, MongoAuditLog::count());

        $fetched = MongoAuditLog::first();
        $this->assertSame('created', $fetched->description);
        $this->assertSame('bar', $fetched->getExtraProperty('foo'));
    }

    #[Group('mongodb-local')]
    public function test_find_returns_null_when_missing(): void
    {
        $this->assertNull($this->store->find((string) (new \MongoDB\BSON\ObjectId())));
    }

    #[Group('mongodb-local')]
    public function test_update_location_updates_an_existing_entry(): void
    {
        $model = $this->store->create(['log_name' => 'default', 'description' => 'visited']);

        $updated = $this->store->updateLocation($model->id, '1.23', '4.56');

        $this->assertTrue($updated);
        $this->assertSame('1.23', $model->fresh()->latitude);
        $this->assertSame('4.56', $model->fresh()->longitude);

        $this->assertFalse($this->store->updateLocation((string) (new \MongoDB\BSON\ObjectId()), '1', '2'));
    }

    #[Group('mongodb-local')]
    public function test_caused_by_filters_by_causer(): void
    {
        $this->store->create(['description' => 'a', 'causer_type' => 'User', 'causer_id' => 1]);
        $this->store->create(['description' => 'b', 'causer_type' => 'User', 'causer_id' => 2]);

        $results = $this->store->causedBy('User', 1);

        $this->assertCount(1, $results);
        $this->assertSame('a', $results->first()->description);
    }

    #[Group('mongodb-local')]
    public function test_for_subject_filters_by_subject(): void
    {
        $this->store->create(['description' => 'a', 'subject_type' => 'Post', 'subject_id' => 1]);
        $this->store->create(['description' => 'b', 'subject_type' => 'Post', 'subject_id' => 2]);

        $results = $this->store->forSubject('Post', 2);

        $this->assertCount(1, $results);
        $this->assertSame('b', $results->first()->description);
    }

    #[Group('mongodb-local')]
    public function test_delete_older_than_removes_only_stale_entries(): void
    {
        Carbon::setTestNow('2020-01-01 00:00:00');
        $this->store->create(['description' => 'old']);

        Carbon::setTestNow('2020-06-01 00:00:00');
        $this->store->create(['description' => 'recent']);

        Carbon::setTestNow();

        $deleted = $this->store->deleteOlderThan(Carbon::parse('2020-03-01 00:00:00'));

        $this->assertSame(1, $deleted);
        $this->assertSame(1, MongoAuditLog::count());
        $this->assertSame('recent', MongoAuditLog::first()->description);
    }

    #[Group('mongodb-local')]
    public function test_subject_and_causer_relations_resolve_through_the_real_driver(): void
    {
        $model = $this->store->create([
            'description' => 'created',
            'subject_type' => MongoAuditLog::class,
            'subject_id' => (string) (new \MongoDB\BSON\ObjectId()),
        ]);

        // subject_type points at a non-existent related row on purpose;
        // this only exercises that morphTo() builds and executes a real
        // query through the mongodb connection without throwing.
        $this->assertNull($model->subject);
    }
}
