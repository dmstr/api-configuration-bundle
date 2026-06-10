<?php

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Command;

use Dmstr\ApiConfiguration\ApiClient\ApiClientFactory;
use Dmstr\ApiConfiguration\Entity\ApiConfiguration;
use Dmstr\ApiPlatformUtils\Service\UuidResolver;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:api:test-connection',
    description: 'Test connection to a configured REST API'
)]
class TestApiConnectionCommand extends Command
{
    public function __construct(
        private readonly UuidResolver $uuidResolver,
        private readonly ApiClientFactory $clientFactory
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('id', InputArgument::REQUIRED, 'API Configuration ID (UUID or partial UUID)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $configId = $input->getArgument('id');

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

        $io->title(sprintf('Testing API Connection: %s', $config->getName()));
        $io->table(
            ['Property', 'Value'],
            [
                ['ID', $config->getId()->toRfc4122()],
                ['Type', $config->getType()],
                ['Endpoint Type', $config->getEndpointType()],
                ['Active', $config->isActive() ? 'Yes' : 'No'],
            ]
        );

        // Only test REST APIs
        if ($config->getEndpointType() !== 'rest') {
            $io->warning('This configuration is not a REST API. Use app:api:validate-file for file-based APIs.');
            return Command::INVALID;
        }

        if (!$config->isActive()) {
            $io->warning('This configuration is disabled.');
        }

        $credentials = $config->getCredentials();
        if ($credentials === null || $credentials === []) {
            $io->error('No credentials configured for this API.');
            return Command::FAILURE;
        }

        $io->section('Authenticating...');

        try {
            $client = $this->clientFactory->create($config->getType(), $credentials);

            if ($client->authenticate()) {
                $io->success('Authentication successful!');

                $io->section('Fetching projects...');
                $projects = $client->getProjects();

                $io->success(sprintf('Successfully fetched %d projects', count($projects)));

                if (count($projects) > 0) {
                    $io->writeln('First 5 projects:');
                    $projectList = array_slice($projects, 0, 5);
                    $rows = [];
                    foreach ($projectList as $project) {
                        $rows[] = [
                            $project['id'] ?? $project['name'] ?? 'N/A',
                            $project['name'] ?? 'N/A',
                        ];
                    }
                    $io->table(['ID', 'Name'], $rows);
                }

                return Command::SUCCESS;
            } else {
                $io->error('Authentication failed. Please check your credentials.');
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error(sprintf('Error: %s', $e->getMessage()));
            if ($output->isVerbose()) {
                $io->writeln($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }
}
