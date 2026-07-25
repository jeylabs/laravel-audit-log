<?php

return [
    /**
     * You can specify the route prefix
     */
    'route_prefix' => 'audit-log',
    /**
     * When user visit every url update audit log
     */
    'record_visiting' => false,

    /*
     * If set to false, no audits will be saved to the database.
     */
    'enabled' => env('AUDIT_LOGGER_ENABLED', true),

    /*
     * When the clean-command is executed, all recording audits older than
     * the number of days specified here will be deleted.
     */
    'delete_records_older_than_days' => 365,

    /*
     * If no log name is passed to the audit() helper
     * we use this default log name.
     */
    'default_log_name' => 'default',

    /*
     * You can specify an auth driver here that gets user models.
     * If this is null we'll use the default Laravel auth driver.
     */
    'default_auth_driver' => null,

    /*
     * If set to true, the subject returns soft deleted models.
     */
    'subject_returns_soft_deleted_models' => false,

    /*
     * This model will be used to log audit. The only requirement is that
     * it should implement \Jeylabs\AuditLog\Contracts\AuditLogModel.
     * Only used by the eloquent driver; kept for backwards compatibility,
     * prefer 'stores.eloquent.model' below.
     */
    'audit_log_model' => \Jeylabs\AuditLog\Models\AuditLog::class,

    /*
   * If set to true, it will store lat/long to the database
   */
    'track_location' => true,

    /*
   * If set to true, it will store ip address to the database
   */
    'track_ip' => true,

    /*
     * Which storage backend to write/read audit logs through.
     * Supported: 'eloquent' (MySQL, PostgreSQL, SQLite, SQL Server),
     * 'mongodb' (requires composer require mongodb/laravel-mongodb),
     * 'dynamodb' (requires composer require async-aws/dynamo-db, or
     * bind your own Jeylabs\AuditLog\Contracts\DynamoDbClient).
     */
    'driver' => env('AUDIT_LOGGER_DRIVER', 'eloquent'),

    'stores' => [

        'eloquent' => [
            /*
             * The Eloquent model used to read/write audit logs. Must
             * implement \Jeylabs\AuditLog\Contracts\AuditLogModel.
             */
            'model' => \Jeylabs\AuditLog\Models\AuditLog::class,
        ],

        'mongodb' => [
            /*
             * The database connection name configured in
             * config/database.php for the mongodb driver.
             */
            'connection' => env('AUDIT_LOGGER_MONGODB_CONNECTION', 'mongodb'),

            /*
             * The Eloquent-compatible model used to read/write audit
             * logs. Must implement
             * \Jeylabs\AuditLog\Contracts\AuditLogModel.
             */
            'model' => \Jeylabs\AuditLog\Models\MongoAuditLog::class,
        ],

        'dynamodb' => [
            'region' => env('AUDIT_LOGGER_DYNAMODB_REGION', 'us-east-1'),

            /*
             * The DynamoDB table name. It must be provisioned ahead of
             * time (this package does not create it) with:
             *   - partition key: id (string)
             *   - a GSI named per 'causer_index' below, partition key
             *     causer_key (string)
             *   - a GSI named per 'subject_index' below, partition key
             *     subject_key (string)
             * See the README for the full schema.
             */
            'table' => env('AUDIT_LOGGER_DYNAMODB_TABLE', 'audit_logs'),

            'causer_index' => 'causer_index',

            'subject_index' => 'subject_index',

            /*
             * Optional custom endpoint, e.g. for DynamoDB Local during
             * development/testing.
             */
            'endpoint' => env('AUDIT_LOGGER_DYNAMODB_ENDPOINT'),

            'credentials' => [
                'key' => env('AUDIT_LOGGER_DYNAMODB_KEY'),
                'secret' => env('AUDIT_LOGGER_DYNAMODB_SECRET'),
            ],

            /*
             * DynamoDB has no efficient range-delete, so
             * `auditlog:clean` does a full table scan for the
             * dynamodb driver. For production-scale cleanup, set this
             * to a number of days to have entries written with a TTL
             * attribute instead, and enable DynamoDB's native TTL on
             * that attribute in the table settings so expired items
             * are removed automatically without any scan.
             */
            'delete_records_older_than_days_via_ttl' => null,

            'ttl_attribute' => 'ttl',
        ],

    ],
];
