<?php

namespace Jeylabs\AuditLog;

use Illuminate\Support\Manager;
use Jeylabs\AuditLog\Contracts\AuditLogStore;
use Jeylabs\AuditLog\Contracts\DynamoDbClient;
use Jeylabs\AuditLog\Exceptions\InvalidConfiguration;
use Jeylabs\AuditLog\Models\AuditLog;
use Jeylabs\AuditLog\Models\MongoAuditLog;
use Jeylabs\AuditLog\Stores\AsyncAwsDynamoDbClient;
use Jeylabs\AuditLog\Stores\DynamoAuditLogStore;
use Jeylabs\AuditLog\Stores\DynamoItemMapper;
use Jeylabs\AuditLog\Stores\EloquentAuditLogStore;

class AuditLogStoreManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config()->get('laravel-audit-log.driver', 'eloquent');
    }

    public function createEloquentDriver(): AuditLogStore
    {
        $model = $this->config()->get('laravel-audit-log.stores.eloquent.model', AuditLog::class);

        return new EloquentAuditLogStore($model);
    }

    public function createMongodbDriver(): AuditLogStore
    {
        if (!class_exists(\MongoDB\Laravel\Eloquent\Model::class)) {
            throw InvalidConfiguration::missingDriverPackage('mongodb', 'mongodb/laravel-mongodb');
        }

        $model = $this->config()->get('laravel-audit-log.stores.mongodb.model', MongoAuditLog::class);

        return new EloquentAuditLogStore($model);
    }

    public function createDynamodbDriver(): AuditLogStore
    {
        $config = $this->config()->get('laravel-audit-log.stores.dynamodb', []);

        $client = $this->container->bound(DynamoDbClient::class)
            ? $this->container->make(DynamoDbClient::class)
            : new AsyncAwsDynamoDbClient([
                'region' => $config['region'] ?? null,
                'endpoint' => $config['endpoint'] ?? null,
                'accessKeyId' => $config['credentials']['key'] ?? null,
                'accessKeySecret' => $config['credentials']['secret'] ?? null,
            ]);

        $mapper = new DynamoItemMapper(
            $config['delete_records_older_than_days_via_ttl'] ?? null,
            $config['ttl_attribute'] ?? 'ttl',
        );

        return new DynamoAuditLogStore(
            $client,
            $mapper,
            $config['table'] ?? 'audit_logs',
            $config['causer_index'] ?? 'causer_index',
            $config['subject_index'] ?? 'subject_index',
        );
    }

    protected function config()
    {
        return $this->container->make('config');
    }
}
