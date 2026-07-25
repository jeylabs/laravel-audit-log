<?php

namespace Jeylabs\AuditLog\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Implemented by every Eloquent-based audit log model (the SQL AuditLog
 * model and the MongoAuditLog model). Used instead of a concrete base
 * class so `audit_log_model` validation isn't tied to a single driver.
 */
interface AuditLogModel
{
    public function subject(): MorphTo;

    public function causer(): MorphTo;

    public function getExtraProperty(string $propertyName): mixed;
}
