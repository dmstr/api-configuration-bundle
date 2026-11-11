<?php
// file generated with AI assistance: Claude Code - 2026-11-11 11:00:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261111100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the optional, unique source_key to dmstr_api_configuration';
    }

    public function up(Schema $schema): void
    {
        // Portable across MySQL 8+, PostgreSQL and SQLite; a unique index
        // accepts any number of NULLs on all three
        $this->addSql('ALTER TABLE dmstr_api_configuration ADD source_key VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_dmstr_api_configuration_source_key ON dmstr_api_configuration (source_key)');
    }

    public function down(Schema $schema): void
    {
        // MySQL scopes index names per table, PostgreSQL and SQLite per schema
        $this->addSql($this->platform instanceof AbstractMySQLPlatform
            ? 'DROP INDEX uniq_dmstr_api_configuration_source_key ON dmstr_api_configuration'
            : 'DROP INDEX uniq_dmstr_api_configuration_source_key');
        $this->addSql('ALTER TABLE dmstr_api_configuration DROP source_key');
    }
}
