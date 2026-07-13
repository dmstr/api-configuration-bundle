<?php
// file generated with AI assistance: Claude Code - 2026-07-01 14:15:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Tests\Migrations;

use Dmstr\ApiConfiguration\Migrations\Version20260516000001;
use Dmstr\ApiConfiguration\Migrations\Version20260608210000;
use Dmstr\ApiConfiguration\Migrations\Version20260713120000;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * Regression guard: the migrations must run on a non-MySQL platform.
 *
 * We execute the real migration classes against an in-memory SQLite database
 * (a platform that is neither MySQL nor PostgreSQL). Any MySQL-only DDL
 * (BINARY(16), TINYINT(1), ENGINE=InnoDB, `RENAME TABLE`, …) would make SQLite
 * throw, so a green test proves the DDL is portable — which is what lets the
 * bundle run on PostgreSQL.
 */
final class MigrationsPortabilityTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        // The ApiConfiguration entity maps its id as the `uuid` type (provided by
        // symfony/uid via the Doctrine bridge). Register it for the standalone
        // migration run — a Symfony app registers it automatically at boot.
        if (!Type::hasType('uuid')) {
            Type::addType('uuid', UuidType::class);
        }

        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
    }

    public function testMigrationsRunOnNonMysqlPlatform(): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $logger = new NullLogger();
        $schema = new Schema();

        // Version20260516000001 — create api_configuration (Schema API).
        $create = new Version20260516000001($this->connection, $logger);
        $create->up($schema);
        foreach ($schema->toSql($platform) as $sql) {
            $this->connection->executeStatement($sql);
        }

        // Version20260608210000 — rename to za7_api_configuration (portable SQL).
        $rename = new Version20260608210000($this->connection, $logger);
        $rename->up($schema);
        foreach ($rename->getSql() as $query) {
            $this->connection->executeStatement($query->getStatement());
        }

        // Version20260713120000 — rename to dmstr_api_configuration (portable SQL).
        $vendorPrefix = new Version20260713120000($this->connection, $logger);
        $vendorPrefix->up($schema);
        foreach ($vendorPrefix->getSql() as $query) {
            $this->connection->executeStatement($query->getStatement());
        }

        $sm = $this->connection->createSchemaManager();
        self::assertTrue($sm->tablesExist(['dmstr_api_configuration']), 'renamed table exists');
        self::assertFalse($sm->tablesExist(['za7_api_configuration']), 'old table name is gone');
        self::assertFalse($sm->tablesExist(['api_configuration']), 'original table name is gone');

        $columns = array_keys($sm->listTableColumns('dmstr_api_configuration'));
        foreach (['id', 'name', 'type', 'endpoint_type', 'config_json', 'active', 'created_at', 'updated_at'] as $column) {
            self::assertContains($column, $columns, sprintf('column %s exists', $column));
        }
    }
}
