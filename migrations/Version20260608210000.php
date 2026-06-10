<?php
// file generated with AI assistance: Claude Code - 2026-06-10 13:00:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260608210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Prefix api_configuration with za7_ to disambiguate from tenant tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('RENAME TABLE api_configuration TO za7_api_configuration');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('RENAME TABLE za7_api_configuration TO api_configuration');
    }
}
