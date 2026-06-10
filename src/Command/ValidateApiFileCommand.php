<?php

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Command;

use Dmstr\ApiConfiguration\Entity\ApiConfiguration;
use Dmstr\ApiPlatformUtils\Service\UuidResolver;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:api:validate-file',
    description: 'Validate a file for a file-based API configuration'
)]
class ValidateApiFileCommand extends Command
{
    public function __construct(
        private readonly UuidResolver $uuidResolver
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

        // Only validate file-based APIs
        if ($config->getEndpointType() !== 'file') {
            $io->warning('This configuration is not a file-based API. Use app:api:test-connection for REST APIs.');
            return Command::INVALID;
        }

        // Check if file exists
        if (!file_exists($filePath)) {
            $io->error(sprintf('File not found: %s', $filePath));
            return Command::FAILURE;
        }

        if (!is_readable($filePath)) {
            $io->error(sprintf('File is not readable: %s', $filePath));
            return Command::FAILURE;
        }

        $fileConfig = $config->getFileConfig();
        if ($fileConfig === null || $fileConfig === []) {
            $io->error('No file configuration found for this API.');
            return Command::FAILURE;
        }

        $io->section('File Information');
        $fileSize = filesize($filePath);
        $io->table(
            ['Property', 'Value'],
            [
                ['Size', sprintf('%s KB', number_format($fileSize / 1024, 2))],
                ['Type', mime_content_type($filePath)],
                ['Expected Format', $fileConfig['format'] ?? 'N/A'],
            ]
        );

        // Basic validation based on type
        $expectedFormat = $fileConfig['format'] ?? 'xml';

        try {
            if ($expectedFormat === 'xml') {
                $io->section('Validating XML...');

                libxml_use_internal_errors(true);
                $xml = simplexml_load_file($filePath);

                if ($xml === false) {
                    $errors = libxml_get_errors();
                    $io->error('XML validation failed:');
                    foreach ($errors as $error) {
                        $io->writeln(sprintf('Line %d: %s', $error->line, trim($error->message)));
                    }
                    libxml_clear_errors();
                    return Command::FAILURE;
                }

                $io->success('XML is valid!');

                // Show basic stats
                $io->section('XML Statistics');
                $io->writeln(sprintf('Root element: %s', $xml->getName()));
                $io->writeln(sprintf('Child elements: %d', count($xml->children())));

            } elseif ($expectedFormat === 'json') {
                $io->section('Validating JSON...');

                $content = file_get_contents($filePath);
                $data = json_decode($content, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $io->error(sprintf('JSON validation failed: %s', json_last_error_msg()));
                    return Command::FAILURE;
                }

                $io->success('JSON is valid!');

                $io->section('JSON Statistics');
                $io->writeln(sprintf('Type: %s', gettype($data)));
                if (is_array($data)) {
                    $io->writeln(sprintf('Elements: %d', count($data)));
                }
            } else {
                $io->warning(sprintf('Unknown format: %s. Skipping format validation.', $expectedFormat));
            }

            $io->success(sprintf('File validation completed successfully for: %s', basename($filePath)));
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error(sprintf('Error during validation: %s', $e->getMessage()));
            if ($output->isVerbose()) {
                $io->writeln($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }
}
