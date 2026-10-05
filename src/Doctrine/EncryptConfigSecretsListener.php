<?php
// file generated with AI assistance: Claude Code - 2026-10-05 10:25:39 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Doctrine;

use Dmstr\ApiConfiguration\Entity\ApiConfiguration;
use Dmstr\ApiConfiguration\Security\ConfigSecrets;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

/**
 * Encrypts the secret keys of every inserted or updated ApiConfiguration
 * right before it is written, whoever changed it (API, console, OAuth token
 * storage of an extension, application code).
 *
 * The entity keeps the encrypted values after the flush, so memory and
 * database hold the same state. Fails with a SecretEncryptionException
 * instead of storing a secret in clear when no usable key is configured.
 */
#[AsDoctrineListener(event: Events::onFlush)]
final class EncryptConfigSecretsListener
{
    public function __construct(
        private readonly ConfigSecrets $secrets,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $entityManager = $args->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();

        $entities = [
            ...$unitOfWork->getScheduledEntityInsertions(),
            ...$unitOfWork->getScheduledEntityUpdates(),
        ];

        foreach ($entities as $entity) {
            if (!$entity instanceof ApiConfiguration) {
                continue;
            }

            $config = $entity->getConfigJson();
            $encrypted = $this->secrets->encrypt($config);
            if ($encrypted === $config) {
                continue;
            }

            $entity->setConfigJson($encrypted);
            $unitOfWork->recomputeSingleEntityChangeSet(
                $entityManager->getClassMetadata(ApiConfiguration::class),
                $entity,
            );
        }
    }
}
