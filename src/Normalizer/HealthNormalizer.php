<?php
// file generated with AI assistance: Claude Code - 2025-11-10 23:45:00

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Normalizer;

use Opis\JsonSchema\Validator;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Normalizes health check responses from different API types into a unified format
 */
class HealthNormalizer
{
    private Validator $validator;
    private string $schemaPath;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        string $projectDir
    ) {
        $this->validator = new Validator();
        $this->schemaPath = $projectDir . '/src/Entity/ApiConfiguration/health.json';
    }

    /**
     * Normalize raw health info from any API client into unified format
     *
     * @param array $rawHealthInfo Raw health data from API client
     * @param string $endpoint The endpoint/URL being tested
     * @param float $responseTime Response time in milliseconds
     * @return array Normalized health information
     * @throws \Exception If validation fails
     */
    public function normalize(array $rawHealthInfo, string $endpoint, float $responseTime): array
    {
        $normalized = [
            'status' => $rawHealthInfo['status'] ?? 'error',
            'authenticated' => $rawHealthInfo['authenticated'] ?? false,
            'endpoint' => $endpoint,
            'responseTime' => round($responseTime, 2),
        ];

        // Add metadata if present
        if (isset($rawHealthInfo['metadata'])) {
            $normalized['metadata'] = $this->normalizeMetadata($rawHealthInfo['metadata']);
        }

        // Add message if present
        if (isset($rawHealthInfo['message'])) {
            $normalized['message'] = $rawHealthInfo['message'];
        }

        // Add error if present
        if (isset($rawHealthInfo['error'])) {
            $normalized['error'] = $this->normalizeError($rawHealthInfo['error']);
        }

        // Validate against schema
        $this->validate($normalized);

        return $normalized;
    }

    /**
     * Normalize metadata section
     */
    private function normalizeMetadata(array $metadata): array
    {
        $normalized = [];

        // Version
        if (isset($metadata['version'])) {
            $normalized['version'] = (string)$metadata['version'];
        }

        // API name
        if (isset($metadata['apiName'])) {
            $normalized['apiName'] = (string)$metadata['apiName'];
        }

        // Rate limit information
        if (isset($metadata['rateLimit'])) {
            $normalized['rateLimit'] = $this->normalizeRateLimit($metadata['rateLimit']);
        }

        // Custom metadata
        if (isset($metadata['custom'])) {
            $normalized['custom'] = $metadata['custom'];
        }

        return $normalized;
    }

    /**
     * Normalize rate limit information
     */
    private function normalizeRateLimit(array $rateLimit): array
    {
        $normalized = [];

        if (isset($rateLimit['limit'])) {
            $normalized['limit'] = (int)$rateLimit['limit'];
        }

        if (isset($rateLimit['remaining'])) {
            $normalized['remaining'] = (int)$rateLimit['remaining'];
        }

        if (isset($rateLimit['reset'])) {
            // Ensure it's a proper ISO 8601 datetime string
            $normalized['reset'] = $this->normalizeDateTime($rateLimit['reset']);
        }

        return $normalized;
    }

    /**
     * Normalize error information
     */
    private function normalizeError(array $error): array
    {
        $normalized = [];

        if (isset($error['code'])) {
            $normalized['code'] = (string)$error['code'];
        }

        if (isset($error['message'])) {
            $normalized['message'] = (string)$error['message'];
        }

        if (isset($error['details'])) {
            $normalized['details'] = $error['details'];
        }

        return $normalized;
    }

    /**
     * Normalize datetime to ISO 8601 format
     */
    private function normalizeDateTime($datetime): string
    {
        if ($datetime instanceof \DateTimeInterface) {
            return $datetime->format(\DateTimeInterface::ATOM);
        }

        if (is_int($datetime)) {
            // Unix timestamp
            return (new \DateTime('@' . $datetime))->format(\DateTimeInterface::ATOM);
        }

        if (is_string($datetime)) {
            try {
                return (new \DateTime($datetime))->format(\DateTimeInterface::ATOM);
            } catch (\Exception $e) {
                return $datetime;
            }
        }

        return (string)$datetime;
    }

    /**
     * Validate normalized data against JSON schema
     *
     * @throws \Exception If validation fails
     */
    private function validate(array $data): void
    {
        $schema = json_decode(file_get_contents($this->schemaPath));
        $result = $this->validator->validate(
            json_decode(json_encode($data)),
            $schema
        );

        if (!$result->isValid()) {
            $formatter = new ErrorFormatter();
            $errors = $formatter->format($result->error());
            throw new \Exception('Health data validation failed: ' . json_encode($errors));
        }
    }
}
