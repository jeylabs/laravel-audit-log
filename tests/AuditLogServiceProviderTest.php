<?php

namespace Jeylabs\AuditLog\Test;

use Jeylabs\AuditLog\AuditLogServiceProvider;
use Jeylabs\AuditLog\Exceptions\InvalidConfiguration;
use Jeylabs\AuditLog\Models\AuditLog;
use Jeylabs\AuditLog\Models\MongoAuditLog;

class AuditLogServiceProviderTest extends TestCase
{
    public function test_determine_audit_log_model_defaults_to_the_sql_model(): void
    {
        $this->assertSame(AuditLog::class, AuditLogServiceProvider::determineAuditLogModel());
    }

    public function test_determine_audit_log_model_follows_the_active_driver(): void
    {
        config(['laravel-audit-log.driver' => 'mongodb']);

        $this->assertSame(MongoAuditLog::class, AuditLogServiceProvider::determineAuditLogModel());
    }

    public function test_determine_audit_log_model_rejects_dynamodb(): void
    {
        config(['laravel-audit-log.driver' => 'dynamodb']);

        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage('dynamodb');

        AuditLogServiceProvider::determineAuditLogModel();
    }

    public function test_determine_audit_log_model_rejects_a_class_not_implementing_the_contract(): void
    {
        config(['laravel-audit-log.stores.eloquent.model' => self::class]);

        $this->expectException(InvalidConfiguration::class);

        AuditLogServiceProvider::determineAuditLogModel();
    }
}
