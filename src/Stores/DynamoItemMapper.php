<?php

namespace Jeylabs\AuditLog\Stores;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Jeylabs\AuditLog\Support\AuditLogEntry;

/**
 * Pure attribute<->item mapping for the dynamodb driver. Deliberately
 * has no SDK dependency so it's trivially unit-testable.
 */
class DynamoItemMapper
{
    public function __construct(
        protected ?int $ttlDays = null,
        protected string $ttlAttribute = 'ttl',
    ) {
    }

    public function toItem(array $attributes): array
    {
        $item = $attributes;

        $item['id'] ??= (string) Str::uuid();

        $now = now();
        $item['created_at'] ??= $now->toIso8601String();
        $item['updated_at'] = $now->toIso8601String();

        if (isset($item['properties']) && $item['properties'] instanceof Collection) {
            $item['properties'] = $item['properties']->toArray();
        }

        if (!empty($item['causer_type']) && isset($item['causer_id'])) {
            $item['causer_key'] = $item['causer_type'] . '#' . $item['causer_id'];
        }

        if (!empty($item['subject_type']) && isset($item['subject_id'])) {
            $item['subject_key'] = $item['subject_type'] . '#' . $item['subject_id'];
        }

        if ($this->ttlDays !== null) {
            $item[$this->ttlAttribute] = $now->copy()->addDays($this->ttlDays)->getTimestamp();
        }

        return $item;
    }

    public function fromItem(?array $item): ?AuditLogEntry
    {
        if ($item === null) {
            return null;
        }

        return new AuditLogEntry($item);
    }

    public static function causerKey(string $causerType, int|string $causerId): string
    {
        return $causerType . '#' . $causerId;
    }

    public static function subjectKey(string $subjectType, int|string $subjectId): string
    {
        return $subjectType . '#' . $subjectId;
    }
}
