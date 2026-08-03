<?php

namespace Jeylabs\AuditLog;

use Illuminate\Auth\AuthManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Jeylabs\AuditLog\Contracts\AuditLogStore;
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

    protected AuditLogStore $store;

    public function __construct(AuthManager $auth, Repository $config, AuditLogStore $store)
    {
        $this->auth = $auth;

        $this->store = $store;

        $this->properties = collect();

        $authDriver = $config['laravel-audit-log']['default_auth_driver'] ?? $auth->getDefaultDriver();

        $this->causedBy = $auth->guard($authDriver)->user();

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

        $attributes = [
            'log_name' => $this->logName,
            'description' => $this->replacePlaceholders($description),
            'properties' => $this->properties,
        ];

        if ($this->performedOn) {
            $attributes['subject_type'] = $this->performedOn->getMorphClass();
            $attributes['subject_id'] = $this->performedOn->getKey();
        }

        if ($this->causedBy) {
            $attributes['causer_type'] = $this->causedBy->getMorphClass();
            $attributes['causer_id'] = $this->causedBy->getKey();
        }

        if (config('laravel-audit-log.track_ip', true)) {
            $attributes['ip'] = request()->ip();
        }

        if (config('laravel-audit-log.track_location', true)) {
            $latitude = $_COOKIE['audit_latitude'] ?? null;
            $longitude = $_COOKIE['audit_longitude'] ?? null;

            $attributes['latitude'] = $latitude;
            $attributes['original_latitude'] = $latitude;
            $attributes['longitude'] = $longitude;
            $attributes['original_longitude'] = $longitude;
        }

        return $this->store->create($attributes);
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

    protected function replacePlaceholders(string $description): string
    {
        return preg_replace_callback('/:[a-z0-9._-]+/i', function ($match) {
            $match = $match[0];

            $attribute = (string) Str::of($match)->between(':', '.');

            if (!in_array($attribute, ['subject', 'causer', 'properties'])) {
                return $match;
            }

            $propertyName = substr($match, strpos($match, '.') + 1);

            $attributeValue = match ($attribute) {
                'subject' => $this->performedOn,
                'causer' => $this->causedBy,
                'properties' => $this->properties,
            };

            if (is_null($attributeValue)) {
                return $match;
            }

            $attributeValue = $attributeValue->toArray();

            return Arr::get($attributeValue, $propertyName, $match);
        }, $description);
    }
}
