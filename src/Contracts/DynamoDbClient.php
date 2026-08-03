<?php

namespace Jeylabs\AuditLog\Contracts;

/**
 * A thin, storage-semantics-first abstraction over DynamoDB, owned by
 * this package rather than borrowed from the AWS SDK. Real
 * implementations translate these calls into AWS SDK / async-aws
 * requests; tests can mock this interface directly without needing
 * any DynamoDB SDK installed.
 */
interface DynamoDbClient
{
    /**
     * Put a single item (a flat or nested associative array of
     * scalar/array attribute values) into the given table.
     */
    public function putItem(string $table, array $item): void;

    /**
     * Fetch a single item by its partition key. Returns null when no
     * item with that key exists.
     */
    public function getItem(string $table, string $keyName, int|string $keyValue): ?array;

    /**
     * Query a global secondary index by its partition key, returning
     * every matching item.
     *
     * @return array<int, array>
     */
    public function queryByIndex(string $table, string $indexName, string $partitionKeyName, string $partitionKeyValue): array;

    /**
     * Scan the full table, returning every item for which the given
     * filter callback returns true. This is an expensive operation on
     * DynamoDB and is only used for the delete-older-than cleanup path.
     *
     * @return array<int, array>
     */
    public function scan(string $table, callable $filter): array;

    /**
     * Delete a single item by its partition key.
     */
    public function deleteItem(string $table, string $keyName, int|string $keyValue): void;
}
