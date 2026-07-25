<?php

namespace Jeylabs\AuditLog\Support;

use ArrayAccess;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * A lightweight, array-backed stand-in for the Eloquent AuditLog model,
 * returned by the dynamodb driver. DynamoDB has no joins, so this
 * intentionally does not expose ->subject / ->causer relations the way
 * the Eloquent and MongoDB drivers do.
 */
class AuditLogEntry implements ArrayAccess
{
    protected array $attributes;

    public function __construct(array $attributes)
    {
        if (isset($attributes['properties']) && !$attributes['properties'] instanceof Collection) {
            $attributes['properties'] = collect($attributes['properties']);
        }

        $this->attributes = $attributes;
    }

    public function __get(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    public function getExtraProperty(string $propertyName): mixed
    {
        $properties = $this->attributes['properties'] ?? collect();

        return Arr::get($properties->toArray(), $propertyName);
    }

    public function toArray(): array
    {
        return $this->attributes;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->attributes[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->attributes[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[$offset]);
    }
}
