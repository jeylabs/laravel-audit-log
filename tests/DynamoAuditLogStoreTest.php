<?php

namespace Jeylabs\AuditLog\Test;

use Illuminate\Support\Carbon;
use Jeylabs\AuditLog\Contracts\DynamoDbClient;
use Jeylabs\AuditLog\Stores\DynamoAuditLogStore;
use Jeylabs\AuditLog\Stores\DynamoItemMapper;
use Jeylabs\AuditLog\Support\AuditLogEntry;

/**
 * Exercises DynamoAuditLogStore against a mock of the package's own
 * DynamoDbClient interface, so this needs no AWS SDK, credentials, or
 * network access.
 */
class DynamoAuditLogStoreTest extends TestCase
{
    protected function makeStore(DynamoDbClient $client): DynamoAuditLogStore
    {
        return new DynamoAuditLogStore($client, new DynamoItemMapper(), 'audit_logs', 'causer_index', 'subject_index');
    }

    public function test_create_puts_the_mapped_item_and_returns_an_entry(): void
    {
        $client = $this->createMock(DynamoDbClient::class);
        $client->expects($this->once())
            ->method('putItem')
            ->with('audit_logs', $this->callback(function (array $item) {
                return $item['description'] === 'created' && !empty($item['id']);
            }));

        $entry = $this->makeStore($client)->create(['description' => 'created']);

        $this->assertInstanceOf(AuditLogEntry::class, $entry);
        $this->assertSame('created', $entry->description);
    }

    public function test_find_returns_null_when_the_client_finds_nothing(): void
    {
        $client = $this->createMock(DynamoDbClient::class);
        $client->method('getItem')->with('audit_logs', 'id', 'missing')->willReturn(null);

        $this->assertNull($this->makeStore($client)->find('missing'));
    }

    public function test_find_wraps_the_found_item(): void
    {
        $client = $this->createMock(DynamoDbClient::class);
        $client->method('getItem')->with('audit_logs', 'id', 'abc')->willReturn(['id' => 'abc', 'description' => 'x']);

        $entry = $this->makeStore($client)->find('abc');

        $this->assertSame('x', $entry->description);
    }

    public function test_update_location_returns_false_when_missing(): void
    {
        $client = $this->createMock(DynamoDbClient::class);
        $client->method('getItem')->willReturn(null);
        $client->expects($this->never())->method('putItem');

        $this->assertFalse($this->makeStore($client)->updateLocation('missing', '1', '2'));
    }

    public function test_update_location_puts_the_updated_item(): void
    {
        $client = $this->createMock(DynamoDbClient::class);
        $client->method('getItem')->willReturn(['id' => 'abc', 'latitude' => null, 'longitude' => null]);
        $client->expects($this->once())
            ->method('putItem')
            ->with('audit_logs', $this->callback(fn (array $item) => $item['latitude'] === '1.1' && $item['longitude'] === '2.2'));

        $this->assertTrue($this->makeStore($client)->updateLocation('abc', '1.1', '2.2'));
    }

    public function test_caused_by_queries_the_causer_index_by_composite_key(): void
    {
        $client = $this->createMock(DynamoDbClient::class);
        $client->expects($this->once())
            ->method('queryByIndex')
            ->with('audit_logs', 'causer_index', 'causer_key', 'App\\Models\\User#42')
            ->willReturn([['id' => '1', 'description' => 'a']]);

        $results = $this->makeStore($client)->causedBy('App\\Models\\User', 42);

        $this->assertCount(1, $results);
        $this->assertSame('a', $results->first()->description);
    }

    public function test_for_subject_queries_the_subject_index_by_composite_key(): void
    {
        $client = $this->createMock(DynamoDbClient::class);
        $client->expects($this->once())
            ->method('queryByIndex')
            ->with('audit_logs', 'subject_index', 'subject_key', 'App\\Models\\Post#7')
            ->willReturn([['id' => '1', 'description' => 'b']]);

        $results = $this->makeStore($client)->forSubject('App\\Models\\Post', 7);

        $this->assertCount(1, $results);
        $this->assertSame('b', $results->first()->description);
    }

    public function test_delete_older_than_scans_and_deletes_matching_items(): void
    {
        $client = $this->createMock(DynamoDbClient::class);
        $client->expects($this->once())
            ->method('scan')
            ->with('audit_logs', $this->isType('callable'))
            ->willReturnCallback(function (string $table, callable $filter) {
                $items = [
                    ['id' => 'old', 'created_at' => '2020-01-01T00:00:00+00:00'],
                    ['id' => 'new', 'created_at' => '2020-06-01T00:00:00+00:00'],
                ];

                return array_values(array_filter($items, $filter));
            });

        $client->expects($this->once())->method('deleteItem')->with('audit_logs', 'id', 'old');

        $deleted = $this->makeStore($client)->deleteOlderThan(Carbon::parse('2020-03-01T00:00:00+00:00'));

        $this->assertSame(1, $deleted);
    }
}
