<?php

namespace Jeylabs\AuditLog\Test;

use Jeylabs\AuditLog\Models\AuditLog;

class AuditLoggerTest extends TestCase
{
    public function test_it_can_log_an_activity_with_a_property_placeholder(): void
    {
        $result = auditLog()->withProperties(['foo' => 'bar'])->log('Hello :properties.foo world');

        $this->assertSame('Hello bar world', $result->description);
        $this->assertNotNull($result->id);

        $fetched = AuditLog::first();
        $this->assertSame('Hello bar world', $fetched->description);
    }

    public function test_it_returns_early_without_writing_when_disabled(): void
    {
        config(['laravel-audit-log.enabled' => false]);

        $result = auditLog()->log('should not be stored');

        $this->assertNull($result);
        $this->assertSame(0, AuditLog::count());
    }
}
