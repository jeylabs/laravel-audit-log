<?php

namespace Jeylabs\AuditLog\Test;

class AuditLogControllerTest extends TestCase
{
    public function test_it_does_nothing_when_record_visiting_is_disabled(): void
    {
        config(['laravel-audit-log.record_visiting' => false]);

        $response = $this->post('/audit-log/push-location', [
            'audit_latitude' => '1.23',
            'audit_longitude' => '4.56',
            'audit_id' => 999,
        ]);

        $response->assertSuccessful();
    }

    public function test_it_updates_the_location_of_an_existing_entry_when_record_visiting_is_enabled(): void
    {
        config(['laravel-audit-log.record_visiting' => true]);

        $entry = auditLog()->log('visited');

        $response = $this->post('/audit-log/push-location', [
            'audit_latitude' => '1.23',
            'audit_longitude' => '4.56',
            'audit_id' => $entry->id,
        ]);

        $response->assertSuccessful();
        $this->assertSame('1.23', $entry->fresh()->latitude);
        $this->assertSame('4.56', $entry->fresh()->longitude);
    }

    public function test_it_does_not_crash_when_the_audit_id_does_not_exist(): void
    {
        config(['laravel-audit-log.record_visiting' => true]);

        $response = $this->post('/audit-log/push-location', [
            'audit_latitude' => '1.23',
            'audit_longitude' => '4.56',
            'audit_id' => 999999,
        ]);

        $response->assertSuccessful();
    }
}
