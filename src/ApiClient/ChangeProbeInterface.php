<?php
// file generated with AI assistance: Claude Code - 2026-10-05 12:10:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\ApiClient;

/**
 * Clients that can tell cheaply whether their source changed.
 *
 * Sources without a reliable account-wide change feed do not implement this
 * interface; callers treat such clients as "always changed".
 */
interface ChangeProbeInterface extends ApiClientInterface
{
    /**
     * Check if the source has been modified since a given timestamp.
     * Used for intelligent sync scheduling (skip when nothing changed).
     *
     * Contract:
     *  - Must be *cheap*: one request at most. It is called to avoid a more
     *    expensive call, so it must not page or fan out.
     *  - Must **fail open**: on any error, or whenever the implementation cannot
     *    tell, return `true`. A `false` suppresses the scan entirely, so a broken
     *    or incomplete probe would silently stop data from being refreshed.
     *
     * @param \DateTimeInterface $since Timestamp to check against
     * @return bool True if source has (or may have) changes since the timestamp
     */
    public function hasChanges(\DateTimeInterface $since): bool;
}
