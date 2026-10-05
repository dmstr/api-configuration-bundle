<?php
// file generated with AI assistance: Claude Code - 2026-10-05 10:25:39 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Serializer;

use Dmstr\ApiConfiguration\Entity\ApiConfiguration;
use Dmstr\ApiConfiguration\Security\ConfigSecrets;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Keeps the secrets of `configJson` out of API responses and preserves them
 * on updates. Wraps the regular (API Platform) normalizer for
 * ApiConfiguration in every format.
 *
 * - normalize: secret values are replaced by {@see ConfigSecrets::MASK},
 *   for every role.
 * - denormalize (PUT/PATCH): an omitted secret or the mask keeps the stored
 *   value, see {@see ConfigSecrets::keepStored()}. Runs before validation,
 *   so the validated configuration is the one that gets stored.
 */
final class ApiConfigurationSecretsNormalizer implements NormalizerInterface, NormalizerAwareInterface, DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;
    use NormalizerAwareTrait;

    private const string ALREADY_CALLED = 'dmstr_api_configuration_secrets_normalizer';
    private const string CONFIG_PROPERTY = 'configJson';

    public function __construct(
        private readonly ConfigSecrets $secrets,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $context[self::ALREADY_CALLED] = true;
        $normalized = $this->normalizer->normalize($data, $format, $context);

        if (is_array($normalized) && is_array($normalized[self::CONFIG_PROPERTY] ?? null)) {
            $normalized[self::CONFIG_PROPERTY] = $this->secrets->mask($normalized[self::CONFIG_PROPERTY]);
        }

        return $normalized;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof ApiConfiguration && !isset($context[self::ALREADY_CALLED]);
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        // PATCH (and non-standard PUT) populate the loaded entity; a standard
        // PUT builds a new object, the stored one is in `previous_data`.
        $previous = $context[AbstractNormalizer::OBJECT_TO_POPULATE]
            ?? $this->requestStack->getCurrentRequest()?->attributes->get('previous_data');
        // Copy before denormalizing, which overwrites the populated object
        $stored = $previous instanceof ApiConfiguration ? $previous->getConfigJson() : null;

        $context[self::ALREADY_CALLED] = true;
        $object = $this->denormalizer->denormalize($data, $type, $format, $context);

        if ($stored !== null && $object instanceof ApiConfiguration) {
            $object->setConfigJson($this->secrets->keepStored($object->getConfigJson(), $stored));
        }

        return $object;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return $type === ApiConfiguration::class && !isset($context[self::ALREADY_CALLED]);
    }

    public function getSupportedTypes(?string $format): array
    {
        // Not cacheable: support depends on the ALREADY_CALLED context flag
        return [ApiConfiguration::class => false];
    }
}
