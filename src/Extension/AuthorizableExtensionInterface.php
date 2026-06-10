<?php
// file generated with AI assistance: Claude Code - 2026-05-17 13:00:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Extension;

use Dmstr\ApiConfiguration\Entity\ApiConfiguration;

/**
 * Implemented by API extensions that support an interactive
 * authorization flow (e.g. OAuth 2.0 authorization-code).
 *
 * Extensions without an interactive flow (static API tokens,
 * file-based imports, ...) MUST NOT implement this interface — the
 * core's authorize-endpoint relies on the absence of this interface
 * to short-circuit with HTTP 409.
 */
interface AuthorizableExtensionInterface
{
    /**
     * Build the URL the end-user must open in their browser to grant
     * access. Returned URL contains the extension's client_id, the
     * caller-supplied redirect URI, scopes, response_type, …
     *
     * @param string|null $state OAuth state parameter, echoed back by the
     *                           provider on the callback. Used by the server
     *                           to correlate the redirect with the originating
     *                           ApiConfiguration.
     *
     * @throws \RuntimeException if the configuration is missing required fields
     */
    public function buildAuthorizeUrl(ApiConfiguration $config, string $redirectUri, ?string $state = null): string;

    /**
     * Exchange the authorization code (returned by the auth provider
     * on the redirect URI) for access/refresh tokens. The extension
     * persists the tokens on the given ApiConfiguration (typically
     * through its own token-storage service).
     *
     * @return array{
     *     expiresAt: ?string,
     *     accountId: ?string,
     *     tokenType: string
     * } Metadata to surface to the caller (no raw tokens).
     *
     * @throws \RuntimeException if the exchange fails or the configuration is invalid
     */
    public function exchangeAuthorizationCode(
        ApiConfiguration $config,
        string $code,
        string $redirectUri,
    ): array;
}
