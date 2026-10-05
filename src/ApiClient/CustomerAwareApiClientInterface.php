<?php
// file generated with AI assistance: Claude Code - 2026-10-05 12:10:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\ApiClient;

/**
 * Clients whose source groups projects by customer.
 *
 * "Customer" maps to: BC2 Groups, BC4 Companies, GitHub Orgs, GitLab Groups,
 * Jira Project Categories. Sources without grouping do not implement this
 * interface; callers check instanceof before calling getCustomers().
 */
interface CustomerAwareApiClientInterface extends ApiClientInterface
{
    /**
     * Get all customers (groups/orgs/companies) from the source.
     *
     * @return array Array of customer data (raw, not normalized)
     */
    public function getCustomers(): array;
}
