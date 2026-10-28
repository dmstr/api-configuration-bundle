<?php
// file generated with AI assistance: Claude Code - 2026-10-28 07:35:37 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Tests\Kernel;

use ApiPlatform\Symfony\Bundle\ApiPlatformBundle;
use Dmstr\ApiConfiguration\ApiConfigurationBundle;
use Dmstr\ApiConfiguration\Tests\Fixtures\AuthorizableDemoExtension;
use Dmstr\ApiConfiguration\Tests\Fixtures\DemoExtension;
use Dmstr\ApiConfiguration\Tests\Fixtures\DemoSchemaProvider;
use Dmstr\ApiConfiguration\Tests\Fixtures\SecretsFixture;
use Dmstr\ApiPlatformUtils\ApiPlatformUtilsBundle;
use Dmstr\OpenApiJsonSchema\OpenApiJsonSchemaBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Minimal application kernel for the HTTP tests of the bundle.
 *
 * - SQLite in memory: every booted kernel starts with an empty database;
 *   the test case creates the schema ({@see \Dmstr\ApiConfiguration\Tests\Http\ApiTestCase}).
 * - A stateless firewall over {@see self::API_PREFIX}, authenticated from
 *   request headers by {@see TestHeaderAuthenticator}.
 * - Two variants, selected by the kernel environment:
 *   {@see self::ENV_WITH_VOTER} registers {@see TestPermissionVoter},
 *   {@see self::ENV_NO_VOTER} registers no custom voter at all.
 *
 * Cache and logs live under the system temp directory, one directory per
 * variant, so a test run leaves no files in the repository.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public const string ENV_WITH_VOTER = 'test';
    public const string ENV_NO_VOTER = 'test_no_voter';

    /** API Platform routes are imported below this prefix, as in an application */
    public const string API_PREFIX = '/api';

    public const string OAUTH_REDIRECT_URI = 'https://app.example.com/api/oauth/callback';

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new DoctrineBundle();
        // Required: the bundle prepends `doctrine_migrations` configuration
        yield new DoctrineMigrationsBundle();
        yield new ApiPlatformBundle();
        yield new ApiPlatformUtilsBundle();
        yield new OpenApiJsonSchemaBundle();
        yield new ApiConfigurationBundle();
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 2);
    }

    public function getCacheDir(): string
    {
        return $this->tmpDir() . '/cache';
    }

    public function getLogDir(): string
    {
        return $this->tmpDir() . '/log';
    }

    /**
     * Not the repository's `config/`: in debug mode FrameworkBundle 7.4+
     * dumps `reference.php` into an existing config directory. This one
     * does not exist, so nothing is written; the kernel reads no files from
     * it ({@see self::configureContainer()} configures everything in code).
     */
    private function getConfigDir(): string
    {
        return $this->tmpDir() . '/config';
    }

    public function hasPermissionVoter(): bool
    {
        return $this->environment !== self::ENV_NO_VOTER;
    }

    private function tmpDir(): string
    {
        return sys_get_temp_dir() . '/dmstr-api-configuration-bundle-tests/' . $this->environment;
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $projectDir = $this->getProjectDir();

        $container->parameters()
            // Autowired by the authorize provider, the redirect listener and the OAuth callback
            ->set('app.oauth.redirect_uri', self::OAUTH_REDIRECT_URI);

        $container->extension('framework', [
            'test' => true,
            'secret' => 'test-secret',
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            // No filesystem cache: the unified schema must follow the fixtures
            'cache' => ['app' => 'cache.adapter.array'],
        ]);

        $container->extension('security', [
            'providers' => [
                'test_users' => ['memory' => ['users' => []]],
            ],
            'firewalls' => [
                'api' => [
                    'pattern' => '^' . self::API_PREFIX,
                    'stateless' => true,
                    'provider' => 'test_users',
                    'custom_authenticators' => [TestHeaderAuthenticator::class],
                ],
            ],
        ]);

        $container->extension('doctrine', [
            'dbal' => [
                'url' => 'sqlite:///:memory:',
            ],
            'orm' => [
                // PHP 8.4 native lazy objects, no symfony/var-exporter needed
                'enable_native_lazy_objects' => true,
                'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
                'mappings' => [
                    'ApiConfiguration' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => $projectDir . '/src/Entity',
                        'prefix' => 'Dmstr\\ApiConfiguration\\Entity',
                    ],
                ],
            ],
        ]);

        $container->extension('api_platform', [
            'title' => 'api-configuration-bundle tests',
            'mapping' => ['paths' => [$projectDir . '/src/Entity']],
            'formats' => [
                'jsonld' => ['application/ld+json'],
                'json' => ['application/json'],
            ],
            'patch_formats' => [
                'json' => ['application/merge-patch+json'],
            ],
            'enable_swagger_ui' => false,
            'enable_re_doc' => false,
        ]);

        $container->extension('dmstr_api_platform_utils', [
            // CredentialEncryption expects the 32 raw bytes, not Base64
            'credential_encryption' => ['key' => SecretsFixture::KEY],
        ]);

        $services = $container->services()
            ->defaults()
            ->autowire()
            ->autoconfigure();

        // Keep the test output free of framework log lines
        $services->set('logger', NullLogger::class);

        $services->set(TestHeaderAuthenticator::class);

        if ($this->hasPermissionVoter()) {
            // Autoconfiguration adds the `security.voter` tag; public so that
            // tests can tell the two variants apart instead of finding an
            // inlined service missing in both
            $services->set(TestPermissionVoter::class)->public();
        }

        // ApiExtensionInterface implementations are tagged by the bundle's
        // autoconfiguration. Schema providers need the tag explicitly: the
        // openapi bundle autotags them only inside its own services file.
        $services->set(DemoExtension::class);
        $services->set(DemoSchemaProvider::class)
            ->tag('dmstr_openapi_json_schema.provider');
        $services->set(AuthorizableDemoExtension::class)
            ->tag('dmstr_openapi_json_schema.provider');
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('.', 'api_platform')->prefix(self::API_PREFIX);
        $routes->import($this->getProjectDir() . '/config/routes.yaml');
    }
}
