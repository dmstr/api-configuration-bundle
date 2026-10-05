<?php
// file generated with AI assistance: Claude Code - 2026-10-05 10:25:39 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Tests\Doctrine;

use Dmstr\ApiConfiguration\Command\EncryptApiConfigurationSecretsCommand;
use Dmstr\ApiConfiguration\Doctrine\EncryptConfigSecretsListener;
use Dmstr\ApiConfiguration\Entity\ApiConfiguration;
use Dmstr\ApiConfiguration\Security\ConfigSecrets;
use Dmstr\ApiConfiguration\Tests\Fixtures\SecretsFixture;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

/**
 * Encryption at rest against a real (in-memory SQLite) database: the
 * onFlush listener and the re-encryption command for existing rows.
 */
final class EncryptConfigSecretsTest extends TestCase
{
    private EntityManager $entityManager;
    private ConfigSecrets $secrets;

    protected function setUp(): void
    {
        if (!Type::hasType('uuid')) {
            Type::addType('uuid', UuidType::class);
        }

        $config = ORMSetup::createAttributeMetadataConfiguration([__DIR__ . '/../../src/Entity'], true);
        // Column names as in a Symfony app (endpoint_type, created_at, ...)
        $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER, true));
        if (method_exists($config, 'enableNativeLazyObjects')) {
            $config->enableNativeLazyObjects(true);
        }
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);
        $this->entityManager = new EntityManager($connection, $config);
        (new SchemaTool($this->entityManager))->createSchema([
            $this->entityManager->getClassMetadata(ApiConfiguration::class),
        ]);

        $this->secrets = SecretsFixture::configSecrets();
        $this->entityManager->getEventManager()->addEventListener(
            Events::onFlush,
            new EncryptConfigSecretsListener($this->secrets),
        );
    }

    public function testInsertAndUpdateStoreSecretsEncrypted(): void
    {
        $entity = (new ApiConfiguration())->setName('demo')->setConfigJson(SecretsFixture::config());
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $column = $this->storedColumn($entity);
        self::assertStringNotContainsString('s3cr3t-password', $column);
        self::assertStringContainsString(ConfigSecrets::PREFIX, $column);
        self::assertTrue(ConfigSecrets::isEncrypted($entity->getConfigJson()['password']), 'entity matches the row');

        $entity->setConfigJson(array_replace($entity->getConfigJson(), ['token' => 'new-token']));
        $this->entityManager->flush();

        $column = $this->storedColumn($entity);
        self::assertStringNotContainsString('new-token', $column);
        self::assertSame('new-token', $this->secrets->decrypt($entity->getConfigJson())['token']);
    }

    public function testCommandEncryptsExistingRowsIdempotently(): void
    {
        // A row written in clear before encryption at rest existed
        $this->entityManager->getConnection()->insert('dmstr_api_configuration', [
            // Converted and bound by the same Doctrine type the ORM uses
            'id' => Uuid::v4(),
            'name' => 'legacy',
            'type' => 'demo',
            'endpoint_type' => 'rest',
            'config_json' => json_encode(SecretsFixture::config()),
            'active' => 1,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ], ['id' => 'uuid']);

        $tester = new CommandTester(new EncryptApiConfigurationSecretsCommand($this->entityManager, $this->secrets));

        self::assertSame(0, $tester->execute(['--dry-run' => true]));
        self::assertStringContainsString('s3cr3t-password', $this->rawColumn('legacy'), 'dry run changes nothing');

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('1 configuration(s)', $tester->getDisplay());
        $encrypted = $this->rawColumn('legacy');
        self::assertStringNotContainsString('s3cr3t-password', $encrypted);

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('nothing to do', $tester->getDisplay());
        self::assertSame($encrypted, $this->rawColumn('legacy'), 'second run leaves encrypted values alone');

        self::assertSame(
            's3cr3t-password',
            $this->secrets->decrypt(json_decode($encrypted, true))['password'],
        );
    }

    private function storedColumn(ApiConfiguration $entity): string
    {
        return $this->rawColumn($entity->getName());
    }

    private function rawColumn(string $name): string
    {
        return (string) $this->entityManager->getConnection()->fetchOne(
            'SELECT config_json FROM dmstr_api_configuration WHERE name = ?',
            [$name],
        );
    }
}
