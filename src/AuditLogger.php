<?php

namespace Jeylabs\AuditLog;

use Illuminate\Auth\AuthManager;
use Illuminate\Database\Eloquent\Model;
use Jeylabs\AuditLog\Models\AuditLog;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Contracts\Config\Repository;
use Jeylabs\AuditLog\Exceptions\CouldNotLogAudit;

class AuditLogger
{
    use Macroable;

    /** @var \Illuminate\Auth\AuthManager */
    protected $auth;

    protected $logName = '';

    /** @var bool */
    protected $logEnabled;

    /** @var \Illuminate\Database\Eloquent\Model */
    protected $performedOn;

    /** @var \Illuminate\Database\Eloquent\Model */
    protected $causedBy;

    /** @var \Illuminate\Support\Collection */
    protected $properties;

    public function __construct(AuthManager $auth, Repository $config)
    {
        $this->auth = $auth;

        $this->properties = collect();

        $authDriver = $config['laravel-audit-log']['default_auth_driver'] ?? $auth->getDefaultDriver();

        if (Str::startsWith(app()->version(), '5.1')) {
            $this->causedBy = $auth->driver($authDriver)->user();
        } else {
            $this->causedBy = $auth->guard($authDriver)->user();
        }

        $this->logName = $config['laravel-audit-log']['default_log_name'];

        $this->logEnabled = $config['laravel-audit-log']['enabled'] ?? true;
    }

    public function performedOn(Model $model)
    {
        $this->performedOn = $model;

        return $this;
    }

    public function on(Model $model)
    {
        return $this->performedOn($model);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model|int|string $modelOrId
     *
     * @return $this
     */
    public function causedBy($modelOrId)
    {
        $model = $this->normalizeCauser($modelOrId);

        $this->causedBy = $model;

        return $this;
    }

    public function by($modelOrId)
    {
        return $this->causedBy($modelOrId);
    }

    /**
     * @param array|\Illuminate\Support\Collection $properties
     *
     * @return $this
     */
    public function withProperties($properties)
    {
        $this->properties = collect($properties);

        return $this;
    }

    /**
     * @param string $key
     * @param mixed $value
     *
     * @return $this
     */
    public function withProperty(string $key, $value)
    {
        $this->properties->put($key, $value);

        return $this;
    }

    public function useLog(string $logName)
    {
        $this->logName = $logName;

        return $this;
    }

    public function inLog(string $logName)
    {
        return $this->useLog($logName);
    }

    /**
     * @param string $description
     *
     * @return null|mixed
     */
    public function log(string $description)
    {
        if (!$this->logEnabled) {
            return;
        }

        $auditLog = AuditLogServiceProvider::getAuditLogModelInstance();

        if ($this->performedOn) {
            $auditLog->subject()->associate($this->performedOn);
        }

        if ($this->causedBy) {
            $auditLog->causer()->associate($this->causedBy);
        }

        $auditLog->properties = $this->properties;

        $auditLog->description = $this->replacePlaceholders($description, $auditLog);

        if (config('laravel-audit-log.track_ip', true)) {
            $auditLog->ip = request()->ip();
        }

        if (config('laravel-audit-log.track_location', true)) {
            $auditLog->latitude = isset($_COOKIE['audit_latitude']) ? $_COOKIE['audit_latitude'] : null;

            $auditLog->original_latitude = isset($_COOKIE['audit_latitude']) ? $_COOKIE['audit_latitude'] : null;

            $auditLog->longitude = isset($_COOKIE['audit_longitude']) ? $_COOKIE['audit_longitude'] : null;

            $auditLog->original_longitude = isset($_COOKIE['audit_longitude']) ? $_COOKIE['audit_longitude'] : null;
        }
        
        $auditLog->log_name = $this->logName;

        $auditLog->save();

        return $auditLog;
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model|int|string $modelOrId
     *
     * @return \Illuminate\Database\Eloquent\Model
     * @throws \Jeylabs\AuditLog\Exceptions\CouldNotLogAudit
     *
     */
    protected function normalizeCauser($modelOrId): Model
    {
        if ($modelOrId instanceof Model) {
            return $modelOrId;
        }

        if ($model = $this->auth->getProvider()->retrieveById($modelOrId)) {
            return $model;
        }

        throw CouldNotLogAudit::couldNotDetermineUser($modelOrId);
    }

    protected function replacePlaceholders(string $description, AuditLog $activity): string
    {
        return preg_replace_callback('/:[a-z0-9._-]+/i', function ($match) use ($activity) {
            $match = $match[0];

            $attribute = (string)string($match)->between(':', '.');

            if (!in_array($attribute, ['subject', 'causer', 'properties'])) {
                return $match;
            }

            $propertyName = substr($match, strpos($match, '.') + 1);

            $attributeValue = $activity->$attribute;

            if (is_null($attributeValue)) {
                return $match;
            }

            $attributeValue = $attributeValue->toArray();

            return array_get($attributeValue, $propertyName, $match);
        }, $description);
    }
}
