<?php
// file generated with AI assistance: Claude Code - 2026-10-05 10:25:39 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Command;

use Dmstr\ApiConfiguration\Entity\ApiConfiguration;
use Dmstr\ApiConfiguration\Security\ConfigSecrets;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Encrypts the secret keys of existing rows, which were stored in clear
 * before secrets were encrypted at rest. Idempotent: encrypted values are
 * left alone, so it can run on every deployment.
 */
#[AsCommand(
    name: 'app:api-configuration:encrypt-secrets',
    description: 'Encrypt the secret keys of all stored API configurations (idempotent)'
)]
class EncryptApiConfigurationSecretsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ConfigSecrets $secrets,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only list the configurations that would be encrypted');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $rows = [];
        foreach ($this->entityManager->getRepository(ApiConfiguration::class)->findAll() as $config) {
            $stored = $config->getConfigJson();
            $encrypted = $this->secrets->encrypt($stored);
            if ($encrypted === $stored) {
                continue;
            }

            $rows[] = [$config->getId()->toRfc4122(), $config->getName(), $config->getType()];
            if (!$dryRun) {
                $config->setConfigJson($encrypted);
            }
        }

        if ($rows === []) {
            $io->success('All secrets are encrypted, nothing to do.');

            return Command::SUCCESS;
        }

        $io->table(['ID', 'Name', 'Type'], $rows);

        if ($dryRun) {
            $io->note(sprintf('Dry run: %d configuration(s) would be encrypted.', count($rows)));

            return Command::SUCCESS;
        }

        $this->entityManager->flush();
        $io->success(sprintf('Encrypted the secrets of %d configuration(s).', count($rows)));

        return Command::SUCCESS;
    }
}
