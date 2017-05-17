<?php

namespace Jeylabs\AuditLog\Traits;

use Jeylabs\AuditLog\AuditLogServiceProvider;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait CausesAudit
{
    public function activity(): MorphMany
    {
        return $this->morphMany(AuditLogServiceProvider::determineAuditLogModel(), 'causer');
    }

    /** @deprecated Use activity() instead */
    public function loggedActivity(): MorphMany
    {
        return $this->activity();
    }
}
