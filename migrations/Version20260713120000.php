<?php
// file generated with AI assistance: Claude Code - 2026-07-13 12:00:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260713120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename za7_api_configuration to dmstr_api_configuration (vendor prefix for bundle tables)';
    }

    public function up(Schema $schema): void
    {
        // Portable across MySQL 8+, PostgreSQL and SQLite (unlike the MySQL-only
        // `RENAME TABLE`, which PostgreSQL does not understand). InnoDB updates
        // foreign keys referencing the renamed table automatically.
        $this->addSql('ALTER TABLE za7_api_configuration RENAME TO dmstr_api_configuration');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dmstr_api_configuration RENAME TO za7_api_configuration');
    }
}
