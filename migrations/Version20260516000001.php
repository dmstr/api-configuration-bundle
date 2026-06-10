<?php
// file generated with AI assistance: Claude Code - 2026-06-10 13:00:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260516000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create api_configuration table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE api_configuration (
                id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                name VARCHAR(255) NOT NULL,
                type VARCHAR(50) NOT NULL,
                endpoint_type VARCHAR(10) NOT NULL,
                config_json JSON NOT NULL,
                active TINYINT(1) NOT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE api_configuration');
    }
}
