<?php

namespace Jeylabs\AuditLog\Test;

use Jeylabs\AuditLog\Exceptions\InvalidConfiguration;
use PHPUnit\Framework\TestCase as BaseTestCase;

class InvalidConfigurationTest extends BaseTestCase
{
    public function test_missing_driver_package_message_names_the_driver_and_package(): void
    {
        $exception = InvalidConfiguration::missingDriverPackage('dynamodb', 'async-aws/dynamo-db');

        $this->assertStringContainsString('dynamodb', $exception->getMessage());
        $this->assertStringContainsString('async-aws/dynamo-db', $exception->getMessage());
    }

    public function test_model_relations_unsupported_message_names_the_driver(): void
    {
        $exception = InvalidConfiguration::modelRelationsUnsupportedForDriver('dynamodb');

        $this->assertStringContainsString('dynamodb', $exception->getMessage());
    }

    public function test_model_is_not_valid_message_names_the_class(): void
    {
        $exception = InvalidConfiguration::modelIsNotValid('App\\NotAnAuditLogModel');

        $this->assertStringContainsString('App\\NotAnAuditLogModel', $exception->getMessage());
    }
}
