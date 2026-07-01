<?php
// file generated with AI assistance: Claude Code - 2026-07-01 14:15:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create the api_configuration table.
 *
 * Written against the DBAL Schema API (not raw platform SQL) so Doctrine emits
 * the correct DDL for whatever platform the consuming app runs on — MySQL,
 * PostgreSQL or SQLite. See MigrationsPortabilityTest.
 */
final class Version20260516000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create api_configuration table';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('api_configuration');
        $table->addColumn('id', 'uuid');
        $table->addColumn('name', 'string', ['length' => 255]);
        $table->addColumn('type', 'string', ['length' => 50]);
        $table->addColumn('endpoint_type', 'string', ['length' => 10]);
        $table->addColumn('config_json', 'json');
        $table->addColumn('active', 'boolean');
        $table->addColumn('created_at', 'datetime_immutable');
        $table->addColumn('updated_at', 'datetime_immutable');
        $table->setPrimaryKey(['id']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('api_configuration');
    }
}
