<?php
// file generated with AI assistance: Claude Code - 2026-05-17 15:00:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\EventListener;

use Dmstr\ApiConfiguration\Entity\ApiConfiguration;
use Dmstr\ApiConfiguration\Extension\AuthorizableExtensionInterface;
use Dmstr\ApiConfiguration\Service\ApiExtensionRegistry;
use Dmstr\ApiPlatformUtils\Service\UuidResolver;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Short-circuits the normal API-Platform pipeline for
 * `GET /api/admin/api_configurations/{id}/authorize` when the caller
 * accepts text/html — replies with a 303 redirect straight to the auth
 * provider so that a direct browser click lands at e.g. Basecamp in one hop.
 *
 * JSON-LD (Hydra) and XHR callers keep the regular pipeline → JSON body
 * with `authorizeUrl`/`state` from {@see ApiConfigurationAuthorizeProvider}.
 *
 * Runs after Symfony Security (priority < 8) so the bearer-token check has
 * already happened; the listener trusts that the request is authorized.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 4)]
final class AuthorizeRedirectListener
{
    private const URI_PATTERN = '#^/api/admin/api_configurations/([^/]+)/authorize/?$#';

    public function __construct(
        private readonly UuidResolver $uuidResolver,
        private readonly ApiExtensionRegistry $extensionRegistry,
        #[Autowire(param: 'app.oauth.redirect_uri')]
        private readonly string $defaultRedirectUri,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        if ($request->getMethod() !== 'GET') {
            return;
        }
        if (!preg_match(self::URI_PATTERN, $request->getPathInfo(), $matches)) {
            return;
        }
        if (!$this->wantsHtml($request)) {
            return;
        }

        $config = $this->uuidResolver->findByPartialUuid(ApiConfiguration::class, $matches[1]);
        if (!$config instanceof ApiConfiguration) {
            return; // let API Platform deliver the 404
        }

        $extension = $this->extensionRegistry->has($config->getType())
            ? $this->extensionRegistry->get($config->getType())
            : null;
        if (!$extension instanceof AuthorizableExtensionInterface) {
            return; // let API Platform deliver the 409
        }

        $redirectUri = (string) ($request->query->get('redirectUri') ?? '');
        if ($redirectUri === '') {
            $redirectUri = $this->defaultRedirectUri;
        }

        $authorizeUrl = $extension->buildAuthorizeUrl($config, $redirectUri, (string) $config->getId());

        $event->setResponse(new RedirectResponse($authorizeUrl, 303));
    }

    private function wantsHtml(Request $request): bool
    {
        $accept = (string) $request->headers->get('Accept', '');
        if ($accept === '' || str_contains($accept, '*/*')) {
            return false;
        }
        $htmlPos = stripos($accept, 'text/html');
        if ($htmlPos === false) {
            return false;
        }
        $jsonPos = stripos($accept, 'application/ld+json');
        if ($jsonPos === false) {
            $jsonPos = stripos($accept, 'application/json');
        }

        return $jsonPos === false || $htmlPos < $jsonPos;
    }
}
