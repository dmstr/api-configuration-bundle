<?php

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\ApiClient;

/**
 * Extended interface for API clients that can enumerate their users/people.
 *
 * Not all sources support user listing (e.g. Jira XML file, GitHub public API)
 * — only implement this where the source provides a reliable people/member list.
 * The UserScanner checks instanceof before calling getUsers().
 */
interface UserAwareApiClientInterface extends ApiClientInterface
{
    /**
     * Get all users/people from the source.
     *
     * @return array<int, array<string, mixed>> Raw user records (not normalized)
     */
    public function getUsers(): array;
}
