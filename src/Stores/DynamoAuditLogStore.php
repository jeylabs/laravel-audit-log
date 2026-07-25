<?php

namespace Jeylabs\AuditLog\Stores;

use DateTimeInterface;
use Illuminate\Support\Collection;
use Jeylabs\AuditLog\Contracts\AuditLogStore;
use Jeylabs\AuditLog\Contracts\DynamoDbClient;
use Jeylabs\AuditLog\Support\AuditLogEntry;

class DynamoAuditLogStore implements AuditLogStore
{
    public function __construct(
        protected DynamoDbClient $client,
        protected DynamoItemMapper $mapper,
        protected string $table,
        protected string $causerIndex = 'causer_index',
        protected string $subjectIndex = 'subject_index',
    ) {
    }

    public function create(array $attributes): AuditLogEntry
    {
        $item = $this->mapper->toItem($attributes);

        $this->client->putItem($this->table, $item);

        return $this->mapper->fromItem($item);
    }

    public function find(int|string $id): ?AuditLogEntry
    {
        return $this->mapper->fromItem($this->client->getItem($this->table, 'id', $id));
    }

    public function updateLocation(int|string $id, ?string $latitude, ?string $longitude): bool
    {
        $item = $this->client->getItem($this->table, 'id', $id);

        if ($item === null) {
            return false;
        }

        $item['latitude'] = $latitude;
        $item['longitude'] = $longitude;
        $item['updated_at'] = now()->toIso8601String();

        $this->client->putItem($this->table, $item);

        return true;
    }

    public function causedBy(string $causerType, int|string $causerId): iterable
    {
        $items = $this->client->queryByIndex(
            $this->table,
            $this->causerIndex,
            'causer_key',
            DynamoItemMapper::causerKey($causerType, $causerId)
        );

        return Collection::make($items)->map(fn (array $item) => $this->mapper->fromItem($item));
    }

    public function forSubject(string $subjectType, int|string $subjectId): iterable
    {
        $items = $this->client->queryByIndex(
            $this->table,
            $this->subjectIndex,
            'subject_key',
            DynamoItemMapper::subjectKey($subjectType, $subjectId)
        );

        return Collection::make($items)->map(fn (array $item) => $this->mapper->fromItem($item));
    }

    /**
     * DynamoDB has no efficient range-delete by date; this performs a
     * full table scan and is only suitable for occasional maintenance
     * runs. For production-scale cleanup, prefer DynamoDB's native TTL
     * (see the 'ttl_attribute' config option) over this method.
     */
    public function deleteOlderThan(DateTimeInterface $cutoff): int
    {
        $cutoffTimestamp = $cutoff->getTimestamp();

        $items = $this->client->scan($this->table, function (array $item) use ($cutoffTimestamp) {
            $createdAt = isset($item['created_at']) ? strtotime((string) $item['created_at']) : false;

            return $createdAt !== false && $createdAt < $cutoffTimestamp;
        });

        foreach ($items as $item) {
            $this->client->deleteItem($this->table, 'id', $item['id']);
        }

        return count($items);
    }
}
