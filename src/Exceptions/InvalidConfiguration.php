<?php

namespace Jeylabs\AuditLog\Exceptions;

use Exception;
use Jeylabs\AuditLog\Contracts\AuditLogModel;

class InvalidConfiguration extends Exception
{
    public static function modelIsNotValid(string $className)
    {
        return new static("The given model class `$className` does not implement `".AuditLogModel::class.'`');
    }

    public static function missingDriverPackage(string $driver, string $package)
    {
        return new static("The `{$driver}` audit log driver requires the `{$package}` package. Install it with `composer require {$package}`.");
    }

    public static function modelRelationsUnsupportedForDriver(string $driver)
    {
        return new static("The `activity()`/`auditLog()` Eloquent relations provided by the CausesAudit/LogsAudit traits are not supported by the `{$driver}` driver, since it has no joins. Query the AuditLogStore's causedBy()/forSubject() methods directly instead.");
    }
}
