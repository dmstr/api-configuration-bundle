<?php
// file generated with AI assistance: Claude Code - 2026-10-28 07:35:37 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Tests\Http;

use Dmstr\ApiConfiguration\Entity\ApiConfiguration;
use Dmstr\ApiConfiguration\Tests\Fixtures\AuthorizableDemoExtension;
use Dmstr\ApiConfiguration\Tests\Fixtures\SecretsFixture;
use Dmstr\ApiConfiguration\Tests\Kernel\TestHeaderAuthenticator;
use Dmstr\ApiConfiguration\Tests\Kernel\TestKernel;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional HTTP tests against {@see TestKernel}: every test gets a fresh
 * kernel with an empty in-memory SQLite schema.
 *
 * Override {@see self::kernelEnvironment()} to run a test class against
 * another kernel variant, e.g. {@see TestKernel::ENV_NO_VOTER}.
 */
abstract class ApiTestCase extends WebTestCase
{
    public const string COLLECTION = TestKernel::API_PREFIX . '/admin/api_configurations';

    protected KernelBrowser $client;

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected static function kernelEnvironment(): string
    {
        return TestKernel::ENV_WITH_VOTER;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient(['environment' => static::kernelEnvironment()]);
        // A reboot would drop the in-memory database between requests
        $this->client->disableReboot();

        $entityManager = $this->entityManager();
        (new SchemaTool($entityManager))->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
    }

    /**
     * Sends a request as a user with the given roles.
     *
     * @param list<string> $roles
     * @param array<string, string> $headers extra headers, e.g. ['Accept' => 'text/html']
     */
    protected function requestAs(
        string $method,
        string $uri,
        array $roles,
        ?array $json = null,
        array $headers = [],
        string $user = TestHeaderAuthenticator::DEFAULT_USER,
    ): Response {
        return $this->request($method, $uri, $json, [
            TestHeaderAuthenticator::HEADER_ROLES => implode(',', $roles),
            TestHeaderAuthenticator::HEADER_USER => $user,
            ...$headers,
        ]);
    }

    /**
     * Sends a request without authentication.
     *
     * @param array<string, string> $headers
     */
    protected function requestAnonymous(string $method, string $uri, ?array $json = null, array $headers = []): Response
    {
        return $this->request($method, $uri, $json, [
            TestHeaderAuthenticator::HEADER_AUTH => TestHeaderAuthenticator::ANONYMOUS,
            ...$headers,
        ]);
    }

    /**
     * Sends a request with exactly the given headers. Defaults: JSON-LD
     * `Accept`, and a body content type matching the method.
     *
     * @param array<string, string> $headers
     */
    protected function request(string $method, string $uri, ?array $json = null, array $headers = []): Response
    {
        $headers += ['Accept' => 'application/ld+json'];
        if ($json !== null) {
            $headers += ['Content-Type' => strtoupper($method) === 'PATCH'
                ? 'application/merge-patch+json'
                : 'application/ld+json'];
        }

        $server = [];
        foreach ($headers as $name => $value) {
            $key = strtoupper(str_replace('-', '_', $name));
            $server[\in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true) ? $key : 'HTTP_' . $key] = $value;
        }

        $this->client->request(
            $method,
            $uri,
            server: $server,
            content: $json === null ? null : json_encode($json, JSON_THROW_ON_ERROR),
        );

        return $this->client->getResponse();
    }

    /**
     * Stores an ApiConfiguration of the `demo` type (secrets encrypted by
     * the bundle's Doctrine listener) and detaches it from the entity manager.
     *
     * @param array<string, mixed> $configOverrides merged into {@see SecretsFixture::config()}
     */
    protected function createApiConfiguration(
        array $configOverrides = [],
        string $name = 'Demo configuration',
        bool $active = true,
    ): ApiConfiguration {
        return $this->persistApiConfiguration(SecretsFixture::config($configOverrides), $name, $active);
    }

    /**
     * Stores an ApiConfiguration of the `authorizable-demo` type, whose
     * extension supports the authorize operation.
     *
     * @param array<string, mixed> $configOverrides merged into {@see AuthorizableDemoExtension::config()}
     */
    protected function createAuthorizableApiConfiguration(
        array $configOverrides = [],
        string $name = 'Authorizable configuration',
    ): ApiConfiguration {
        return $this->persistApiConfiguration(AuthorizableDemoExtension::config($configOverrides), $name, true);
    }

    /**
     * @param array<string, mixed> $configJson
     */
    protected function persistApiConfiguration(array $configJson, string $name, bool $active): ApiConfiguration
    {
        $configuration = (new ApiConfiguration())
            ->setName($name)
            ->setConfigJson($configJson)
            ->setActive($active);

        $entityManager = $this->entityManager();
        $entityManager->persist($configuration);
        $entityManager->flush();
        $entityManager->clear();

        return $configuration;
    }

    protected function itemUri(ApiConfiguration $configuration, string $suffix = ''): string
    {
        return self::COLLECTION . '/' . $configuration->getId()->toRfc4122() . $suffix;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function json(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    protected function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get('doctrine.orm.entity_manager');
    }
}
