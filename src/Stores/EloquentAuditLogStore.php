<?php

namespace Jeylabs\AuditLog\Stores;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Jeylabs\AuditLog\Contracts\AuditLogStore;

/**
 * Backs both the 'eloquent' driver (MySQL, PostgreSQL, SQLite, SQL
 * Server, via the SQL AuditLog model) and the 'mongodb' driver (via
 * MongoAuditLog, which extends mongodb/laravel-mongodb's
 * Eloquent-compatible base model). Which model class is used is the
 * only thing that differs between the two drivers.
 */
class EloquentAuditLogStore implements AuditLogStore
{
    public function __construct(protected string $modelClass)
    {
    }

    public function getModelClass(): string
    {
        return $this->modelClass;
    }

    public function create(array $attributes): Model
    {
        $model = $this->newModelInstance();

        $model->forceFill($attributes)->save();

        return $model;
    }

    public function find(int|string $id): ?Model
    {
        return $this->modelClass::find($id);
    }

    public function updateLocation(int|string $id, ?string $latitude, ?string $longitude): bool
    {
        $model = $this->find($id);

        if (!$model) {
            return false;
        }

        $model->latitude = $latitude;
        $model->longitude = $longitude;

        return $model->save();
    }

    public function causedBy(string $causerType, int|string $causerId): iterable
    {
        return $this->modelClass::query()
            ->where('causer_type', $causerType)
            ->where('causer_id', $causerId)
            ->get();
    }

    public function forSubject(string $subjectType, int|string $subjectId): iterable
    {
        return $this->modelClass::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->get();
    }

    public function deleteOlderThan(DateTimeInterface $cutoff): int
    {
        // Model::fromDateTime() is the connection-aware conversion: SQL
        // models format it as a grammar-matching string, while
        // MongoAuditLog (backed by mongodb/laravel-mongodb) returns a
        // MongoDB\BSON\UTCDateTime. A hardcoded string comparison works
        // for SQL but silently matches nothing against Mongo's
        // BSON-typed created_at, since BSON compares by type first.
        $cutoff = $this->newModelInstance()->fromDateTime($cutoff);

        return $this->modelClass::query()
            ->where('created_at', '<', $cutoff)
            ->delete();
    }

    protected function newModelInstance(): Model
    {
        return new $this->modelClass();
    }
}
