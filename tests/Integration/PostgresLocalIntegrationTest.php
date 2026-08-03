<?php

namespace Jeylabs\AuditLog\Test\Integration;

use Illuminate\Support\Carbon;
use Jeylabs\AuditLog\Models\AuditLog;
use Jeylabs\AuditLog\Stores\EloquentAuditLogStore;
use Jeylabs\AuditLog\Test\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Round-trips the eloquent driver against a live PostgreSQL server instead
 * of sqlite standing in for it. The unit tests already cover the same
 * EloquentAuditLogStore code path against sqlite (Eloquent is
 * DB-agnostic), so this exists to catch anything sqlite's more lenient
 * typing papers over (e.g. real timestamp/bigint-unsigned comparisons).
 * Opt-in: skips itself unless POSTGRES_LOCAL_DSN is set, e.g.
 *
 *   POSTGRES_LOCAL_DSN=pgsql://user:pass@127.0.0.1:5432/audit_log_it composer test-postgres-local
 */
class PostgresLocalIntegrationTest extends TestCase
{
    protected EloquentAuditLogStore $store;

    protected function setUp(): void
    {
        if (!getenv('POSTGRES_LOCAL_DSN')) {
            $this->markTestSkipped('Set POSTGRES_LOCAL_DSN to run against a running PostgreSQL server.');
        }

        parent::setUp();

        $this->store = new EloquentAuditLogStore(AuditLog::class);
    }

    protected function defineEnvironment($app): void
    {
        $dsn = getenv('POSTGRES_LOCAL_DSN');
        $parts = parse_url($dsn);

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'pgsql',
            'host' => $parts['host'],
            'port' => $parts['port'] ?? 5432,
            'database' => ltrim($parts['path'] ?? '', '/'),
            'username' => $parts['user'] ?? 'postgres',
            'password' => $parts['pass'] ?? '',
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'public',
        ]);
    }

    #[Group('postgres-local')]
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

        $this->assertInstanceOf(AuditLog::class, $model);
        $this->assertTrue($model->exists);
        $this->assertSame(1, AuditLog::count());

        $fetched = AuditLog::first();
        $this->assertSame('created', $fetched->description);
        $this->assertSame('bar', $fetched->getExtraProperty('foo'));
    }

    #[Group('postgres-local')]
    public function test_find_returns_null_when_missing(): void
    {
        $this->assertNull($this->store->find(999));
    }

    #[Group('postgres-local')]
    public function test_update_location_updates_an_existing_entry(): void
    {
        $model = $this->store->create(['log_name' => 'default', 'description' => 'visited']);

        $updated = $this->store->updateLocation($model->id, '1.23', '4.56');

        $this->assertTrue($updated);
        $this->assertSame('1.23', $model->fresh()->latitude);
        $this->assertSame('4.56', $model->fresh()->longitude);

        $this->assertFalse($this->store->updateLocation(999, '1', '2'));
    }

    #[Group('postgres-local')]
    public function test_caused_by_filters_by_causer(): void
    {
        $this->store->create(['description' => 'a', 'causer_type' => 'User', 'causer_id' => 1]);
        $this->store->create(['description' => 'b', 'causer_type' => 'User', 'causer_id' => 2]);

        $results = $this->store->causedBy('User', 1);

        $this->assertCount(1, $results);
        $this->assertSame('a', $results->first()->description);
    }

    #[Group('postgres-local')]
    public function test_for_subject_filters_by_subject(): void
    {
        $this->store->create(['description' => 'a', 'subject_type' => 'Post', 'subject_id' => 1]);
        $this->store->create(['description' => 'b', 'subject_type' => 'Post', 'subject_id' => 2]);

        $results = $this->store->forSubject('Post', 2);

        $this->assertCount(1, $results);
        $this->assertSame('b', $results->first()->description);
    }

    #[Group('postgres-local')]
    public function test_delete_older_than_removes_only_stale_entries(): void
    {
        Carbon::setTestNow('2020-01-01 00:00:00');
        $this->store->create(['description' => 'old']);

        Carbon::setTestNow('2020-06-01 00:00:00');
        $this->store->create(['description' => 'recent']);

        Carbon::setTestNow();

        $deleted = $this->store->deleteOlderThan(Carbon::parse('2020-03-01 00:00:00'));

        $this->assertSame(1, $deleted);
        $this->assertSame(1, AuditLog::count());
        $this->assertSame('recent', AuditLog::first()->description);
    }
}
