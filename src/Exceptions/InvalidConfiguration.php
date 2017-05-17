<?php

namespace Jeylabs\AuditLog\Exceptions;

use Exception;
use Jeylabs\AuditLog\Models\AuditLog;

class InvalidConfiguration extends Exception
{
    public static function modelIsNotValid(string $className)
    {
        return new static("The given model class `$className` does not extend `".AuditLog::class.'`');
    }
}
