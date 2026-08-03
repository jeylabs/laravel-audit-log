<?php

namespace Jeylabs\AuditLog\Models;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Jeylabs\AuditLog\Contracts\AuditLogModel;
use MongoDB\Laravel\Eloquent\Builder;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\MorphTo;

/**
 * MongoDB-backed equivalent of the SQL AuditLog model, for the
 * 'mongodb' driver. Requires the mongodb/laravel-mongodb package
 * (composer require mongodb/laravel-mongodb) and the PHP mongodb
 * extension, neither of which are hard dependencies of this package.
 */
class MongoAuditLog extends Model implements AuditLogModel
{
    protected $connection = 'mongodb';

    protected $collection = 'audit_logs';

    protected $casts = [
        'properties' => 'collection',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function causer(): MorphTo
    {
        return $this->morphTo();
    }

    public function getExtraProperty(string $propertyName): mixed
    {
        return Arr::get($this->properties->toArray(), $propertyName);
    }

    public function getChangesAttribute(): Collection
    {
        return collect(array_filter($this->properties->toArray(), function ($key) {
            return in_array($key, ['attributes', 'old']);
        }, ARRAY_FILTER_USE_KEY));
    }

    public function scopeInLog(Builder $query, ...$logNames): Builder
    {
        if (is_array($logNames[0])) {
            $logNames = $logNames[0];
        }

        return $query->whereIn('log_name', $logNames);
    }

    public function scopeCausedBy(Builder $query, $causer): Builder
    {
        return $query
            ->where('causer_type', get_class($causer))
            ->where('causer_id', $causer->getKey());
    }

    public function scopeForSubject(Builder $query, $subject): Builder
    {
        return $query
            ->where('subject_type', get_class($subject))
            ->where('subject_id', $subject->getKey());
    }
}
