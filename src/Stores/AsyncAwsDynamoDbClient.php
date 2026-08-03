<?php

namespace Jeylabs\AuditLog\Stores;

use AsyncAws\Core\Configuration;
use AsyncAws\DynamoDb\DynamoDbClient as AsyncAwsClient;
use AsyncAws\DynamoDb\Enum\Select;
use AsyncAws\DynamoDb\ValueObject\AttributeValue;
use Jeylabs\AuditLog\Contracts\DynamoDbClient;
use Jeylabs\AuditLog\Exceptions\InvalidConfiguration;

/**
 * Default real-world implementation of DynamoDbClient, backed by the
 * lightweight async-aws/dynamo-db package (not the full aws/aws-sdk-php).
 * Only instantiated when the dynamodb driver is actually resolved, so
 * apps that don't use it never need the package installed.
 */
class AsyncAwsDynamoDbClient implements DynamoDbClient
{
    protected AsyncAwsClient $client;

    public function __construct(array $config = [])
    {
        if (!class_exists(AsyncAwsClient::class)) {
            throw InvalidConfiguration::missingDriverPackage('dynamodb', 'async-aws/dynamo-db');
        }

        $this->client = new AsyncAwsClient(Configuration::create($config));
    }

    public function putItem(string $table, array $item): void
    {
        $this->client->putItem([
            'TableName' => $table,
            'Item' => $this->marshal($item),
        ]);
    }

    public function getItem(string $table, string $keyName, int|string $keyValue): ?array
    {
        $result = $this->client->getItem([
            'TableName' => $table,
            'Key' => $this->marshal([$keyName => $keyValue]),
        ]);

        $item = $result->getItem();

        return $item ? $this->unmarshal($item) : null;
    }

    public function queryByIndex(string $table, string $indexName, string $partitionKeyName, string $partitionKeyValue): array
    {
        $result = $this->client->query([
            'TableName' => $table,
            'IndexName' => $indexName,
            'Select' => Select::ALL_ATTRIBUTES,
            'KeyConditionExpression' => '#pk = :pk',
            'ExpressionAttributeNames' => ['#pk' => $partitionKeyName],
            'ExpressionAttributeValues' => $this->marshal([':pk' => $partitionKeyValue]),
        ]);

        $items = [];
        foreach ($result->getItems() as $item) {
            $items[] = $this->unmarshal($item);
        }

        return $items;
    }

    public function scan(string $table, callable $filter): array
    {
        $items = [];

        $result = $this->client->scan(['TableName' => $table]);

        foreach ($result->getItems() as $item) {
            $unmarshalled = $this->unmarshal($item);

            if ($filter($unmarshalled)) {
                $items[] = $unmarshalled;
            }
        }

        return $items;
    }

    public function deleteItem(string $table, string $keyName, int|string $keyValue): void
    {
        $this->client->deleteItem([
            'TableName' => $table,
            'Key' => $this->marshal([$keyName => $keyValue]),
        ]);
    }

    /**
     * @return array<string, AttributeValue>
     */
    protected function marshal(array $data): array
    {
        $marshalled = [];

        foreach ($data as $key => $value) {
            $marshalled[$key] = $this->marshalValue($value);
        }

        return $marshalled;
    }

    protected function marshalValue(mixed $value): AttributeValue
    {
        return match (true) {
            is_null($value) => new AttributeValue(['NULL' => true]),
            is_bool($value) => new AttributeValue(['BOOL' => $value]),
            is_int($value) || is_float($value) => new AttributeValue(['N' => (string) $value]),
            is_array($value) && array_is_list($value) => new AttributeValue([
                'L' => array_map(fn ($v) => $this->marshalValue($v), $value),
            ]),
            is_array($value) => new AttributeValue(['M' => $this->marshal($value)]),
            default => new AttributeValue(['S' => (string) $value]),
        };
    }

    /**
     * @param array<string, AttributeValue> $item
     */
    protected function unmarshal(array $item): array
    {
        $data = [];

        foreach ($item as $key => $value) {
            $data[$key] = $this->unmarshalValue($value);
        }

        return $data;
    }

    protected function unmarshalValue(AttributeValue $value): mixed
    {
        // getL()/getM() are declared non-nullable and default to an
        // empty array when unset, so they can't be distinguished from
        // an actually-empty list/map by null-checking; !empty() is used
        // for those two instead, checked last since every other type
        // has a proper nullable accessor.
        return match (true) {
            $value->getNull() !== null => null,
            $value->getBool() !== null => $value->getBool(),
            $value->getN() !== null => str_contains($value->getN(), '.') ? (float) $value->getN() : (int) $value->getN(),
            $value->getS() !== null => $value->getS(),
            !empty($value->getL()) => array_map(fn ($v) => $this->unmarshalValue($v), $value->getL()),
            !empty($value->getM()) => $this->unmarshal($value->getM()),
            default => null,
        };
    }
}
