<?php
// file generated with AI assistance: Claude Code - 2026-05-17 13:30:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Dmstr\ApiConfiguration\Entity\ApiConfiguration;
use Dmstr\ApiConfiguration\Extension\AuthorizableExtensionInterface;
use Dmstr\ApiConfiguration\Service\ApiExtensionRegistry;
use Dmstr\ApiPlatformUtils\Service\UuidResolver;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns the authorization URL the end-user must open in their
 * browser to grant access for an ApiConfiguration whose extension
 * supports an interactive authorization flow.
 *
 * GET /api/admin/api_configurations/{id}/authorize
 *
 * The redirect URI defaults to the server-side OAuth callback (env
 * `OAUTH_REDIRECT_URI`); callers MAY override it with `?redirectUri=…`
 * for headless / scripted flows.
 */
final class ApiConfigurationAuthorizeProvider implements ProviderInterface
{
    public function __construct(
        private readonly UuidResolver $uuidResolver,
        private readonly ApiExtensionRegistry $extensionRegistry,
        private readonly RequestStack $requestStack,
        #[Autowire(param: 'app.oauth.redirect_uri')]
        private readonly string $defaultRedirectUri,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
    {
        $id = $uriVariables['id'] ?? null;
        if ($id instanceof \Symfony\Component\Uid\Uuid) {
            $id = $id->toRfc4122();
        }
        if (!is_string($id) || $id === '') {
            throw new BadRequestHttpException('Missing path parameter "id"');
        }

        $config = $this->uuidResolver->findByPartialUuid(ApiConfiguration::class, $id);
        if (!$config instanceof ApiConfiguration) {
            throw new NotFoundHttpException(sprintf('ApiConfiguration "%s" not found', $id));
        }

        $request = $this->requestStack->getCurrentRequest();
        $redirectUri = (string) ($request?->query->get('redirectUri') ?? '');
        if ($redirectUri === '') {
            $redirectUri = $this->defaultRedirectUri;
        }

        $extension = $this->extensionRegistry->has($config->getType())
            ? $this->extensionRegistry->get($config->getType())
            : null;
        if (!$extension instanceof AuthorizableExtensionInterface) {
            throw new ConflictHttpException(sprintf(
                'Extension "%s" does not support interactive authorization',
                $config->getType(),
            ));
        }

        $state = (string) $config->getId();

        $result = new \stdClass();
        $result->authorizeUrl = $extension->buildAuthorizeUrl($config, $redirectUri, $state);
        $result->redirectUri = $redirectUri;
        $result->state = $state;
        $result->extensionType = $config->getType();

        return $result;
    }
}
