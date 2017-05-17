<?php

namespace Jeylabs\AuditLog\Exceptions;

use Exception;

class CouldNotLogAudit extends Exception
{
    public static function couldNotDetermineUser($id)
    {
        return new static("Could not determine a user with identifier `{$id}`.");
    }
}
