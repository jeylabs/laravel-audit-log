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
     * it should be or extend the \Jeylabs\AuditLog\Models\AuditLog model.
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
];
