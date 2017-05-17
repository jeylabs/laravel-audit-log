<?php

use Jeylabs\AuditLog\AuditLogger;

if (! function_exists('activity')) {
    function activity(string $logName = null): AuditLogger
    {
        $defaultLogName = config('laravel-audit-log.default_log_name');
        return app(AuditLogger::class)->useLog($logName ?? $defaultLogName);
    }
}

if (! function_exists('auditLog')) {
    function auditLog(string $logName = null): AuditLogger
    {
        $defaultLogName = config('laravel-audit-log.default_log_name');
        return app(AuditLogger::class)->useLog($logName ?? $defaultLogName);
    }
}
