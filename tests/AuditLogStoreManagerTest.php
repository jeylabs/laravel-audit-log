<?php

namespace Jeylabs\AuditLog\Test;

use Jeylabs\AuditLog\Contracts\AuditLogStore;
use Jeylabs\AuditLog\Contracts\DynamoDbClient;
use Jeylabs\AuditLog\Models\AuditLog;
use Jeylabs\AuditLog\Models\MongoAuditLog;
use Jeylabs\AuditLog\Stores\AsyncAwsDynamoDbClient;
use Jeylabs\AuditLog\Stores\DynamoAuditLogStore;
use Jeylabs\AuditLog\Stores\EloquentAuditLogStore;

class AuditLogStoreManagerTest extends TestCase
{
    public function test_default_driver_is_eloquent_using_the_sql_audit_log_model(): void
    {
        $store = $this->app->make(AuditLogStore::class);

        $this->assertInstanceOf(EloquentAuditLogStore::class, $store);
        $this->assertSame(AuditLog::class, $store->getModelClass());
    }

    public function test_mongodb_driver_resolves_to_an_eloquent_store_using_the_mongo_model(): void
    {
        config(['laravel-audit-log.driver' => 'mongodb']);

        $store = $this->app->make(AuditLogStore::class);

        $this->assertInstanceOf(EloquentAuditLogStore::class, $store);
        $this->assertSame(MongoAuditLog::class, $store->getModelClass());
    }

    public function test_dynamodb_driver_defaults_to_the_async_aws_client_without_throwing(): void
    {
        config(['laravel-audit-log.driver' => 'dynamodb']);
        config(['laravel-audit-log.stores.dynamodb.region' => 'us-east-1']);

        $store = $this->app->make(AuditLogStore::class);

        $this->assertInstanceOf(DynamoAuditLogStore::class, $store);

        $property = new \ReflectionProperty(DynamoAuditLogStore::class, 'client');
        $property->setAccessible(true);
        $this->assertInstanceOf(AsyncAwsDynamoDbClient::class, $property->getValue($store));
    }

    public function test_dynamodb_driver_resolves_when_a_client_is_bound(): void
    {
        config(['laravel-audit-log.driver' => 'dynamodb']);

        $this->app->instance(DynamoDbClient::class, $this->createMock(DynamoDbClient::class));

        $store = $this->app->make(AuditLogStore::class);

        $this->assertInstanceOf(DynamoAuditLogStore::class, $store);
    }
}
