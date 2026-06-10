<?php
// file generated with AI assistance: Claude Code - 2025-11-02 00:00:00

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Command;

use Dmstr\ApiConfiguration\Entity\ApiConfiguration;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'app:api:create',
    description: 'Create a new API configuration from JSON'
)]
class CreateApiConfigurationCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Configuration name')
            ->addArgument('config-json', InputArgument::REQUIRED, 'Configuration JSON (as string or file path with @ prefix)')
            ->addOption('active', null, InputOption::VALUE_NONE, 'Set configuration as active')
            ->setHelp(<<<'HELP'
Create a new API configuration using JSON.

Usage:
  # From JSON string
  bin/console app:api:create "My Basecamp Config" '{"basecamp2":true,"type":"basecamp2","endpoint_type":"rest","account_id":"123456","username":"user@example.com","password":"secret","app_name":"ZA7","app_contact":"admin@example.com"}'

  # From file
  bin/console app:api:create "My Basecamp Config" @/path/to/config.json

The JSON must match one of the supported API types (basecamp2, github, gitlab).
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = $input->getArgument('name');
        $configJsonInput = $input->getArgument('config-json');

        $io->title('Create API Configuration');

        // Parse config JSON from string or file
        try {
            $configJson = $this->parseConfigJson($configJsonInput);
        } catch (\Exception $e) {
            $io->error(sprintf('Failed to parse config JSON: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        // Create entity
        $config = new ApiConfiguration();
        $config->setName($name);
        $config->setConfigJson($configJson);
        $config->setActive($input->getOption('active'));

        // Validate using Symfony validator (which will trigger ApiConfigurationConstraint)
        $violations = $this->validator->validate($config);

        if (count($violations) > 0) {
            $io->error('Validation failed:');
            foreach ($violations as $violation) {
                $io->writeln(sprintf(' - %s: %s', $violation->getPropertyPath(), $violation->getMessage()));
            }
            return Command::FAILURE;
        }

        // Save configuration
        try {
            $this->entityManager->persist($config);
            $this->entityManager->flush();

            $io->success('API configuration created successfully!');
            $io->table(
                ['Property', 'Value'],
                [
                    ['ID', $config->getId()->toRfc4122()],
                    ['Name', $config->getName()],
                    ['Type', $config->getType()],
                    ['Endpoint Type', $config->getEndpointType()],
                    ['Active', $config->isActive() ? 'Yes' : 'No'],
                ]
            );

            if ($output->isVerbose()) {
                $io->section('Config JSON');
                $io->writeln(json_encode($config->getConfigJson(), JSON_PRETTY_PRINT));
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error(sprintf('Failed to create configuration: %s', $e->getMessage()));
            if ($output->isVerbose()) {
                $io->writeln($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }

    private function parseConfigJson(string $input): array
    {
        // Check if input starts with @ (file path)
        if (str_starts_with($input, '@')) {
            $filePath = substr($input, 1);

            if (!file_exists($filePath)) {
                throw new \RuntimeException(sprintf('Config file not found: %s', $filePath));
            }

            if (!is_readable($filePath)) {
                throw new \RuntimeException(sprintf('Config file not readable: %s', $filePath));
            }

            $jsonContent = file_get_contents($filePath);

            if ($jsonContent === false) {
                throw new \RuntimeException(sprintf('Failed to read config file: %s', $filePath));
            }

            $input = $jsonContent;
        }

        // Parse JSON
        $configJson = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(sprintf('Invalid JSON: %s', json_last_error_msg()));
        }

        if (!is_array($configJson)) {
            throw new \RuntimeException('Config JSON must be an object');
        }

        return $configJson;
    }
}
