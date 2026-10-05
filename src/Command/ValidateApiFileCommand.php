<?php
// file generated with AI assistance: Claude Code - 2026-10-05 12:40:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Command;

use Dmstr\ApiConfiguration\ApiClient\ApiClientFactory;
use Dmstr\ApiConfiguration\ApiClient\FileApiClientInterface;
use Dmstr\ApiConfiguration\Entity\ApiConfiguration;
use Dmstr\ApiPlatformUtils\Service\UuidResolver;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Checks a file against a file-based API configuration (e.g. a Jira XML
 * export) before it is dropped into the import directory.
 *
 * Validation and parsing are delegated to the type's client
 * ({@see FileApiClientInterface::validateFile()} and
 * {@see FileApiClientInterface::parseFile()}), so the command knows no
 * format of its own. The file the import currently uses is covered by
 * `app:api-configuration:health`.
 */
#[AsCommand(
    name: 'app:api:validate-file',
    description: 'Validate a file for a file-based API configuration'
)]
class ValidateApiFileCommand extends Command
{
    public function __construct(
        private readonly UuidResolver $uuidResolver,
        private readonly ApiClientFactory $clientFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('id', InputArgument::REQUIRED, 'API Configuration ID (UUID or partial UUID)')
            ->addArgument('file-path', InputArgument::REQUIRED, 'Path to file to validate');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $configId = $input->getArgument('id');
        $filePath = $input->getArgument('file-path');

        // Find configuration by ID (supports partial UUID)
        try {
            $config = $this->uuidResolver->findByPartialUuid(ApiConfiguration::class, $configId);
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        if ($config === null) {
            $io->error(sprintf('No API configuration found with ID starting with: %s', $configId));
            return Command::FAILURE;
        }

        $io->title(sprintf('Validating File: %s', $config->getName()));
        $io->table(
            ['Property', 'Value'],
            [
                ['ID', $config->getId()->toRfc4122()],
                ['Type', $config->getType()],
                ['Endpoint Type', $config->getEndpointType()],
                ['File', $filePath],
            ]
        );

        try {
            $client = $this->clientFactory->createFromEntity($config);
        } catch (\Exception $e) {
            $io->error(sprintf('Cannot create the client: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        if (!$client instanceof FileApiClientInterface) {
            $io->warning('This configuration is not a file-based API. Use app:api:test-connection for REST APIs.');
            return Command::INVALID;
        }

        if (!$client->validateFile($filePath)) {
            $io->error(sprintf(
                'File is missing, not readable or not a supported format (%s): %s',
                implode(', ', $client->getSupportedFormats()),
                $filePath,
            ));
            return Command::FAILURE;
        }

        try {
            $parsed = $client->parseFile($filePath);
        } catch (\Exception $e) {
            $io->error(sprintf('Parsing failed: %s', $e->getMessage()));
            if ($output->isVerbose()) {
                $io->writeln($e->getTraceAsString());
            }
            return Command::FAILURE;
        }

        $io->section('Parse Result');
        $rows = [['Size', sprintf('%s KB', number_format((filesize($filePath) ?: 0) / 1024, 2))]];
        foreach ($parsed['metadata'] ?? [] as $key => $value) {
            $rows[] = [$key, is_scalar($value) ? (string) $value : json_encode($value)];
        }
        if (isset($parsed['items']) && is_array($parsed['items'])) {
            $rows[] = ['Items', count($parsed['items'])];
        }
        $io->table(['Property', 'Value'], $rows);

        $io->success(sprintf('File validation completed successfully for: %s', basename($filePath)));
        return Command::SUCCESS;
    }
}
