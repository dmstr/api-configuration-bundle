<?php
// file generated with AI assistance: Claude Code - 2025-11-10 23:58:00

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Command;

use Dmstr\ApiConfiguration\ApiClient\ApiClientFactory;
use Dmstr\ApiConfiguration\Entity\ApiConfiguration;
use Dmstr\ApiConfiguration\Normalizer\HealthNormalizer;
use Dmstr\ApiPlatformUtils\Service\UuidResolver;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:api-configuration:health',
    description: 'Check health status of an API configuration'
)]
class ApiConfigurationHealthCommand extends Command
{
    public function __construct(
        private readonly UuidResolver $uuidResolver,
        private readonly ApiClientFactory $clientFactory,
        private readonly HealthNormalizer $healthNormalizer
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

        $io->title(sprintf('Health Check: %s', $config->getName()));
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

        $io->section('Performing Health Check...');

        // Measure response time
        $startTime = microtime(true);
        $client = null;

        try {
            // Create API client
            $client = $this->clientFactory->createFromEntity($config);

            // Get health info from client
            $rawHealthInfo = $client->getHealthInfo();

            // Calculate response time
            $responseTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

            // Normalize health info — endpoint comes from the client itself
            // (mirrors ApiConfigurationHealthProvider)
            $normalizedHealth = $this->healthNormalizer->normalize(
                $rawHealthInfo,
                $client->getEndpoint(),
                $responseTime
            );

            // Display results
            $this->displayHealthResult($io, $normalizedHealth);

            return $normalizedHealth['status'] === 'ok' ? Command::SUCCESS : Command::FAILURE;
        } catch (\Exception $e) {
            $responseTime = (microtime(true) - $startTime) * 1000;

            // Fall back to a URN identifying the ApiConfiguration when the
            // client could not be created — still a valid URI per RFC 8141.
            $endpoint = $client?->getEndpoint()
                ?? sprintf('urn:za7:api-configuration:%s', $config->getId());

            $normalizedHealth = $this->healthNormalizer->normalize(
                [
                    'status' => 'error',
                    'authenticated' => false,
                    'message' => 'Health check failed: ' . $e->getMessage(),
                    'error' => [
                        'code' => 'HEALTH_CHECK_ERROR',
                        'message' => $e->getMessage(),
                    ],
                ],
                $endpoint,
                $responseTime
            );

            $this->displayHealthResult($io, $normalizedHealth);

            if ($output->isVerbose()) {
                $io->writeln("\n" . $e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    /**
     * Display health check result
     */
    private function displayHealthResult(SymfonyStyle $io, array $healthData): void
    {
        // Overall status
        if ($healthData['status'] === 'ok') {
            $io->success('Health Check PASSED');
        } else {
            $io->error('Health Check FAILED');
        }

        // Basic info
        $rows = [
            ['Status', strtoupper($healthData['status'])],
            ['Authenticated', $healthData['authenticated'] ? 'Yes' : 'No'],
            ['Endpoint', $healthData['endpoint']],
            ['Response Time', sprintf('%.2f ms', $healthData['responseTime'])],
        ];

        if (isset($healthData['message'])) {
            $rows[] = ['Message', $healthData['message']];
        }

        $io->table(['Property', 'Value'], $rows);

        // Metadata
        if (isset($healthData['metadata'])) {
            $io->section('Metadata');
            $metadataRows = [];

            if (isset($healthData['metadata']['apiName'])) {
                $metadataRows[] = ['API Name', $healthData['metadata']['apiName']];
            }

            if (isset($healthData['metadata']['version'])) {
                $metadataRows[] = ['Version', $healthData['metadata']['version']];
            }

            if (isset($healthData['metadata']['rateLimit'])) {
                $rateLimit = $healthData['metadata']['rateLimit'];
                $metadataRows[] = ['Rate Limit', sprintf(
                    '%d / %d remaining',
                    $rateLimit['remaining'] ?? 0,
                    $rateLimit['limit'] ?? 0
                )];
                if (isset($rateLimit['reset'])) {
                    $metadataRows[] = ['Rate Limit Reset', $rateLimit['reset']];
                }
            }

            if (isset($healthData['metadata']['custom'])) {
                foreach ($healthData['metadata']['custom'] as $key => $value) {
                    if (is_scalar($value) || $value === null) {
                        $metadataRows[] = [ucfirst($key), $value ?? 'N/A'];
                    }
                }
            }

            if (!empty($metadataRows)) {
                $io->table(['Property', 'Value'], $metadataRows);
            }
        }

        // Error details
        if (isset($healthData['error'])) {
            $io->section('Error Details');
            $errorRows = [];

            if (isset($healthData['error']['code'])) {
                $errorRows[] = ['Code', $healthData['error']['code']];
            }

            if (isset($healthData['error']['message'])) {
                $errorRows[] = ['Message', $healthData['error']['message']];
            }

            $io->table(['Property', 'Value'], $errorRows);
        }
    }
}
