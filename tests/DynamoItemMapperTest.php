<?php

namespace Jeylabs\AuditLog\Test;

use Illuminate\Support\Carbon;
use Jeylabs\AuditLog\Stores\DynamoItemMapper;

class DynamoItemMapperTest extends TestCase
{
    public function test_to_item_generates_an_id_and_timestamps(): void
    {
        $mapper = new DynamoItemMapper();

        $item = $mapper->toItem(['description' => 'created']);

        $this->assertNotEmpty($item['id']);
        $this->assertNotEmpty($item['created_at']);
        $this->assertNotEmpty($item['updated_at']);
        $this->assertSame('created', $item['description']);
    }

    public function test_to_item_preserves_an_existing_id_and_created_at(): void
    {
        $mapper = new DynamoItemMapper();

        $item = $mapper->toItem(['id' => 'fixed-id', 'created_at' => '2020-01-01T00:00:00+00:00']);

        $this->assertSame('fixed-id', $item['id']);
        $this->assertSame('2020-01-01T00:00:00+00:00', $item['created_at']);
    }

    public function test_to_item_flattens_a_properties_collection(): void
    {
        $mapper = new DynamoItemMapper();

        $item = $mapper->toItem(['properties' => collect(['foo' => 'bar'])]);

        $this->assertIsArray($item['properties']);
        $this->assertSame(['foo' => 'bar'], $item['properties']);
    }

    public function test_to_item_builds_composite_causer_and_subject_keys(): void
    {
        $mapper = new DynamoItemMapper();

        $item = $mapper->toItem([
            'causer_type' => 'App\\Models\\User',
            'causer_id' => 42,
            'subject_type' => 'App\\Models\\Post',
            'subject_id' => 7,
        ]);

        $this->assertSame('App\\Models\\User#42', $item['causer_key']);
        $this->assertSame('App\\Models\\Post#7', $item['subject_key']);
    }

    public function test_to_item_omits_composite_keys_when_no_causer_or_subject(): void
    {
        $mapper = new DynamoItemMapper();

        $item = $mapper->toItem(['description' => 'no causer or subject']);

        $this->assertArrayNotHasKey('causer_key', $item);
        $this->assertArrayNotHasKey('subject_key', $item);
    }

    public function test_ttl_attribute_is_set_when_ttl_days_configured(): void
    {
        Carbon::setTestNow('2020-01-01 00:00:00');

        $mapper = new DynamoItemMapper(ttlDays: 30, ttlAttribute: 'expires_at');

        $item = $mapper->toItem(['description' => 'expires']);

        Carbon::setTestNow();

        $this->assertSame(Carbon::parse('2020-01-31 00:00:00')->getTimestamp(), $item['expires_at']);
    }

    public function test_ttl_attribute_is_absent_by_default(): void
    {
        $mapper = new DynamoItemMapper();

        $item = $mapper->toItem(['description' => 'no ttl']);

        $this->assertArrayNotHasKey('ttl', $item);
    }

    public function test_from_item_returns_null_for_null(): void
    {
        $mapper = new DynamoItemMapper();

        $this->assertNull($mapper->fromItem(null));
    }

    public function test_from_item_wraps_the_item_in_an_entry(): void
    {
        $mapper = new DynamoItemMapper();

        $entry = $mapper->fromItem(['id' => '1', 'description' => 'x', 'properties' => ['foo' => 'bar']]);

        $this->assertSame('1', $entry->id);
        $this->assertSame('x', $entry->description);
        $this->assertSame('bar', $entry->getExtraProperty('foo'));
    }

    public function test_causer_and_subject_key_helpers(): void
    {
        $this->assertSame('User#1', DynamoItemMapper::causerKey('User', 1));
        $this->assertSame('Post#7', DynamoItemMapper::subjectKey('Post', 7));
    }
}
