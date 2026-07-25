<?php

namespace Jeylabs\AuditLog\Test;

use Carbon\Carbon;
use Jeylabs\AuditLog\Models\AuditLog;
use Jeylabs\AuditLog\Stores\EloquentAuditLogStore;

/**
 * Exercises the store used by the eloquent driver against a real
 * sqlite database. Since Eloquent is DB-agnostic, this is the same
 * code path MySQL and PostgreSQL run through.
 */
class EloquentAuditLogStoreTest extends TestCase
{
    protected EloquentAuditLogStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = new EloquentAuditLogStore(AuditLog::class);
    }

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
    }

    public function test_find_returns_null_when_missing(): void
    {
        $this->assertNull($this->store->find(999));
    }

    public function test_update_location_returns_false_when_missing(): void
    {
        $this->assertFalse($this->store->updateLocation(999, '1.23', '4.56'));
    }

    public function test_update_location_updates_an_existing_entry(): void
    {
        $model = $this->store->create(['log_name' => 'default', 'description' => 'visited']);

        $updated = $this->store->updateLocation($model->id, '1.23', '4.56');

        $this->assertTrue($updated);
        $this->assertSame('1.23', $model->fresh()->latitude);
        $this->assertSame('4.56', $model->fresh()->longitude);
    }

    public function test_caused_by_filters_by_causer(): void
    {
        $this->store->create(['description' => 'a', 'causer_type' => 'User', 'causer_id' => 1]);
        $this->store->create(['description' => 'b', 'causer_type' => 'User', 'causer_id' => 2]);

        $results = $this->store->causedBy('User', 1);

        $this->assertCount(1, $results);
        $this->assertSame('a', $results->first()->description);
    }

    public function test_for_subject_filters_by_subject(): void
    {
        $this->store->create(['description' => 'a', 'subject_type' => 'Post', 'subject_id' => 1]);
        $this->store->create(['description' => 'b', 'subject_type' => 'Post', 'subject_id' => 2]);

        $results = $this->store->forSubject('Post', 2);

        $this->assertCount(1, $results);
        $this->assertSame('b', $results->first()->description);
    }

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
