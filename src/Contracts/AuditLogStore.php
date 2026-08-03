<?php

namespace Jeylabs\AuditLog\Contracts;

use DateTimeInterface;

interface AuditLogStore
{
    /**
     * Persist a new audit log entry and return the stored representation
     * (an Eloquent model for the eloquent/mongodb drivers, an
     * AuditLogEntry for the dynamodb driver).
     */
    public function create(array $attributes): mixed;

    /**
     * Find a single audit log entry by its primary key.
     */
    public function find(int|string $id): mixed;

    /**
     * Update the latitude/longitude of an existing entry.
     * Returns false if no entry with the given id exists.
     */
    public function updateLocation(int|string $id, ?string $latitude, ?string $longitude): bool;

    /**
     * All entries caused by the given causer.
     */
    public function causedBy(string $causerType, int|string $causerId): iterable;

    /**
     * All entries performed on the given subject.
     */
    public function forSubject(string $subjectType, int|string $subjectId): iterable;

    /**
     * Delete every entry created before the given cutoff and return
     * the number of entries deleted.
     */
    public function deleteOlderThan(DateTimeInterface $cutoff): int;
}
