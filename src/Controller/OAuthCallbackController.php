<?php
// file generated with AI assistance: Claude Code - 2026-05-17 14:30:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Controller;

use Dmstr\ApiConfiguration\Entity\ApiConfiguration;
use Dmstr\ApiConfiguration\Extension\AuthorizableExtensionInterface;
use Dmstr\ApiConfiguration\Service\ApiExtensionRegistry;
use Psr\Log\LoggerInterface;
use Dmstr\ApiPlatformUtils\Service\UuidResolver;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Server-side OAuth callback. The auth provider redirects the user's browser
 * here with `?code=…&state=<api_configuration_id>`. We look up the
 * ApiConfiguration via the state parameter, delegate the token exchange to
 * the matching AuthorizableExtension, and render a small HTML status page.
 *
 * Route is intentionally outside `/api/admin` so the unauthenticated browser
 * redirect from the auth provider is allowed (see security.yaml).
 */
final class OAuthCallbackController
{
    public function __construct(
        private readonly UuidResolver $uuidResolver,
        private readonly ApiExtensionRegistry $extensionRegistry,
        private readonly LoggerInterface $logger,
        #[Autowire(param: 'app.oauth.redirect_uri')]
        private readonly string $defaultRedirectUri,
    ) {
    }

    #[Route('/api/oauth/callback', name: 'oauth_callback', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $code = (string) $request->query->get('code', '');
        $state = (string) $request->query->get('state', '');
        $error = (string) $request->query->get('error', '');

        if ($error !== '') {
            return $this->renderStatus(
                400,
                'Authorization denied',
                sprintf('The authorization provider returned an error: %s', $error),
            );
        }

        if ($code === '' || $state === '') {
            return $this->renderStatus(
                400,
                'Missing parameters',
                'The callback URL is missing the required code/state parameters.',
            );
        }

        $config = $this->uuidResolver->findByPartialUuid(ApiConfiguration::class, $state);
        if (!$config instanceof ApiConfiguration) {
            return $this->renderStatus(
                404,
                'Unknown configuration',
                sprintf('No ApiConfiguration found for state "%s".', $state),
            );
        }

        $extension = $this->extensionRegistry->has($config->getType())
            ? $this->extensionRegistry->get($config->getType())
            : null;
        if (!$extension instanceof AuthorizableExtensionInterface) {
            return $this->renderStatus(
                409,
                'Authorization not supported',
                sprintf('Extension "%s" does not support interactive authorization.', $config->getType()),
            );
        }

        try {
            $meta = $extension->exchangeAuthorizationCode($config, $code, $this->defaultRedirectUri);
        } catch (\Throwable $e) {
            $this->logger->error('OAuth callback: token exchange failed', [
                'config_id' => (string) $config->getId(),
                'extension' => $config->getType(),
                'error' => $e->getMessage(),
            ]);

            return $this->renderStatus(
                400,
                'Token exchange failed',
                $e->getMessage(),
            );
        }

        $this->logger->info('OAuth callback: authorization succeeded', [
            'config_id' => (string) $config->getId(),
            'extension' => $config->getType(),
            'expires_at' => $meta['expiresAt'] ?? null,
        ]);

        return $this->renderStatus(
            200,
            'Authorized ✓',
            sprintf(
                'ApiConfiguration "%s" (%s) authorized successfully. Tokens persisted, expires at %s. You can close this tab.',
                htmlspecialchars($config->getName(), ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($config->getType(), ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) ($meta['expiresAt'] ?? 'unknown'), ENT_QUOTES, 'UTF-8'),
            ),
        );
    }

    private function renderStatus(int $httpStatus, string $title, string $message): Response
    {
        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{$title}</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 640px; margin: 4em auto; padding: 2em; }
        h1 { margin: 0 0 .5em; }
        p { line-height: 1.5; }
        .status-2 h1 { color: #16a34a; }
        .status-4 h1, .status-5 h1 { color: #dc2626; }
    </style>
</head>
<body class="status-{$this->statusFamily($httpStatus)}">
    <h1>{$title}</h1>
    <p>{$message}</p>
</body>
</html>
HTML;

        return new Response($html, $httpStatus, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    private function statusFamily(int $status): int
    {
        return (int) floor($status / 100);
    }
}
