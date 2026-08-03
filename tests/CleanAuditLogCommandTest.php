<?php

namespace Jeylabs\AuditLog\Test;

use Carbon\Carbon;
use Jeylabs\AuditLog\Models\AuditLog;

class CleanAuditLogCommandTest extends TestCase
{
    public function test_it_deletes_entries_older_than_the_configured_number_of_days(): void
    {
        config(['laravel-audit-log.delete_records_older_than_days' => 30]);

        Carbon::setTestNow(now()->subDays(60));
        auditLog()->log('too old');

        Carbon::setTestNow(now()->addDays(59));
        auditLog()->log('kept');

        Carbon::setTestNow();

        $this->artisan('auditlog:clean')->assertSuccessful();

        $this->assertSame(1, AuditLog::count());
        $this->assertSame('kept', AuditLog::first()->description);
    }
}
