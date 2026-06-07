<?php

declare(strict_types=1);

namespace Hive;

use Hive\Config\Resolver\ArrayConfigResolver;
use Hive\Config\Resolver\ConfigResolverInterface;
use Hive\DependencyInjection\Container;
use Hive\DependencyInjection\Exceptions\ContainerException;
use Hive\DependencyInjection\Provider\ServiceProviderInterface;
use LogicException;

class Application
{
    private const string DEFAULT_ENVIRONMENT = 'production';

    private ConfigResolverInterface $config;

    /** @var list<string|ServiceProviderInterface> */
    private array $providers = [];

    private string $environment;

    private ?Container $container = null;

    private function __construct()
    {
        $this->config = new ArrayConfigResolver;
        $this->environment = $this->detectEnvironment();
    }

    /**
     * Starts the configuration of a new application.
     */
    public static function configure(): self
    {
        return new self;
    }

    /**
     * Set the configuration source. An array is wrapped in an
     * ArrayConfigResolver.
     *
     * @param  ConfigResolverInterface|array<string, mixed>  $config
     */
    public function withConfig(ConfigResolverInterface|array $config): self
    {
        $this->assertNotCreated();
        $this->config = is_array($config) ? new ArrayConfigResolver($config) : $config;

        return $this;
    }

    /**
     * Register service providers (class names or instances).
     * Can be called multiple times.
     *
     * @param  list<string|ServiceProviderInterface>  $providers
     */
    public function withProviders(array $providers): self
    {
        $this->assertNotCreated();
        $this->providers = [...$this->providers, ...$providers];

        return $this;
    }

    /**
     * Set the environment explicitly (this overrides auto-detection).
     */
    public function withEnvironment(string $environment): self
    {
        $this->assertNotCreated();
        $this->environment = $environment;

        return $this;
    }

    /**
     * Builds the container, registers the provider, and boots the application.
     * Idempotent: a second call returns the same instance.
     */
    public function create(): self
    {
        if ($this->container instanceof Container) {
            return $this;
        }

        $container = new Container($this->config);

        // App, Container-Config und Environment im Container verfügbar machen.
        $container->instance(self::class, $this);
        $container->instance(ConfigResolverInterface::class, $this->config);

        foreach ($this->providers as $provider) {
            $container->addProvider($this->resolveProvider($provider));
        }

        $container->boot();

        $this->container = $container;

        return $this;
    }

    public function container(): Container
    {
        if (! $this->container instanceof Container) {
            throw new LogicException('Application has not been created yet. Call create() first.');
        }

        return $this->container;
    }

    /**
     * Shortcut for $app->container()->get($id).
     */
    public function make(string $id): mixed
    {
        return $this->container()->get($id);
    }

    public function config(): ConfigResolverInterface
    {
        return $this->config;
    }

    // ---------------------------------------------------------------------
    // Environment
    // ---------------------------------------------------------------------

    public function environment(): string
    {
        return $this->environment;
    }

    public function isEnvironment(string ...$environments): bool
    {
        return in_array($this->environment, $environments, true);
    }

    public function isProduction(): bool
    {
        return $this->environment === 'production';
    }

    public function isLocal(): bool
    {
        return $this->environment === 'local';
    }

    // ---------------------------------------------------------------------
    // Internal
    // ---------------------------------------------------------------------
    private function resolveProvider(string|ServiceProviderInterface $provider): ServiceProviderInterface
    {
        if ($provider instanceof ServiceProviderInterface) {
            return $provider;
        }

        if (! class_exists($provider)) {
            throw new ContainerException(sprintf('Service provider class "%s" does not exist.', $provider));
        }

        $instance = new $provider;

        if (! $instance instanceof ServiceProviderInterface) {
            throw new ContainerException(sprintf(
                'Service provider "%s" must implement %s.',
                $provider,
                ServiceProviderInterface::class,
            ));
        }

        return $instance;
    }

    private function detectEnvironment(): string
    {
        $env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV');

        return is_string($env) && $env !== '' ? $env : self::DEFAULT_ENVIRONMENT;
    }

    private function assertNotCreated(): void
    {
        if ($this->container instanceof Container) {
            throw new LogicException('Application is already created; configuration is locked.');
        }
    }
}
