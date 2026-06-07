<?php

declare(strict_types=1);

namespace Hive\DependencyInjection;

use Closure;
use Hive\Config\Resolver\ArrayConfigResolver;
use Hive\Config\Resolver\ConfigResolverInterface;
use Hive\DependencyInjection\Attribute\Config;
use Hive\DependencyInjection\Attribute\Inject;
use Hive\DependencyInjection\Exceptions\CircularDependencyException;
use Hive\DependencyInjection\Exceptions\ContainerException;
use Hive\DependencyInjection\Exceptions\NotFoundException;
use Hive\DependencyInjection\Provider\BootableServiceProviderInterface;
use Hive\DependencyInjection\Provider\ServiceProviderInterface;
use Psr\Container\ContainerInterface;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * PSR-11 dependency injection container with auto-wiring, deferred
 * instantiation (lazy by default), attribute-based injection, circular
 * dependency detection, and service providers.
 */
class Container implements ContainerInterface
{
    /** @var array<string, ServiceDefinition> */
    private array $definitions = [];

    /** @var array<string, mixed> resolved singleton-instances. */
    private array $shared = [];

    /** @var array<string, true> IDs of the current resolving services (cycle detection). */
    private array $building = [];

    /** @var list<string> current resolving path (for error messages). */
    private array $path = [];

    /** @var array<class-string, ReflectionClass<object>> */
    private array $reflectionCache = [];

    /** @var list<ServiceProviderInterface> */
    private array $providers = [];

    private bool $booted = false;

    public function __construct(
        private readonly ConfigResolverInterface $config = new ArrayConfigResolver,
    ) {
        // Den Container selbst auflösbar machen.
        $this->shared[ContainerInterface::class] = $this;
        $this->shared[self::class] = $this;
    }

    // ---------------------------------------------------------------------
    // Registration API
    // ---------------------------------------------------------------------

    /**
     * Registers any ServiceDefinition under an ID.
     */
    public function define(string $id, ServiceDefinition $definition): self
    {
        $this->definitions[$id] = $definition;
        unset($this->shared[$id]); // discard any outdated singleton instances

        return $this;
    }

    /**
     * Singleton binding. $concrete can be a class, a factory, or null
     * (meaning $id is the class itself).
     *
     * @param  class-string|Closure|null  $concrete
     */
    public function singleton(string $id, string|Closure|null $concrete = null): self
    {
        return $this->define($id, $this->toDefinition($id, $concrete, Lifetime::Singleton));
    }

    /**
     * Transient Bindings (new instance on each get()).
     *
     * @param  class-string|Closure|null  $concrete
     */
    public function bind(string $id, string|Closure|null $concrete = null): self
    {
        return $this->define($id, $this->toDefinition($id, $concrete, Lifetime::Transient));
    }

    /**
     * Explicit Factory-Binding.
     */
    public function factory(string $id, Closure $factory, Lifetime $lifetime = Lifetime::Transient): self
    {
        return $this->define($id, ServiceDefinition::forFactory($factory, $lifetime));
    }

    /**
     * Save an existing instance or value.
     */
    public function instance(string $id, mixed $value): self
    {
        $this->definitions[$id] = ServiceDefinition::forValue($value);
        $this->shared[$id] = $value;

        return $this;
    }

    /**
     * Alias: $id is resolved to $targetId (e.g., interface -> implementation).
     */
    public function alias(string $id, string $targetId): self
    {
        return $this->define($id, ServiceDefinition::forAlias($targetId));
    }

    /**
     * @param  class-string|Closure|null  $concrete
     */
    private function toDefinition(string $id, string|Closure|null $concrete, Lifetime $lifetime): ServiceDefinition
    {
        if ($concrete instanceof Closure) {
            return ServiceDefinition::forFactory($concrete, $lifetime);
        }

        /** @var class-string $class */
        $class = $concrete ?? $id;

        return ServiceDefinition::forClass($class, $lifetime);
    }

    // ---------------------------------------------------------------------
    // ServiceProvider
    // ---------------------------------------------------------------------

    public function addProvider(ServiceProviderInterface $provider): self
    {
        $this->providers[] = $provider;

        return $this;
    }

    /**
     * Executes the registration phase for all providers, followed by the
     * boot phase (only for BootableServiceProviderInterface), after which
     * all definitions marked as “eager” are instantiated.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        foreach ($this->providers as $provider) {
            $provider->register($this);
        }

        foreach ($this->providers as $provider) {
            if ($provider instanceof BootableServiceProviderInterface) {
                $provider->boot($this);
            }
        }

        $this->booted = true;

        foreach ($this->definitions as $id => $definition) {
            if ($definition->eager && ! isset($this->shared[$id])) {
                $this->get($id);
            }
        }
    }

    // ---------------------------------------------------------------------
    // PSR-11
    // ---------------------------------------------------------------------

    /**
     * @template T of object
     *
     * @param  class-string<T>|string  $id
     * @return ($id is class-string<T> ? T : mixed)
     */
    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->shared)) {
            return $this->shared[$id];
        }

        if (isset($this->building[$id])) {
            throw CircularDependencyException::fromPath([...$this->path, $id]);
        }

        $definition = $this->definitionFor($id);

        $this->building[$id] = true;
        $this->path[] = $id;

        try {
            $instance = $this->build($definition);
        } finally {
            array_pop($this->path);
            unset($this->building[$id]);
        }

        if ($definition->isShared()) {
            $this->shared[$id] = $instance;
        }

        return $instance;
    }

    /**
     * @param  string|class-string  $id
     */
    public function has(string $id): bool
    {
        if (array_key_exists($id, $this->shared) || isset($this->definitions[$id])) {
            return true;
        }

        return $this->isInstantiable($id);
    }

    // ---------------------------------------------------------------------
    // Resolving
    // ---------------------------------------------------------------------

    private function definitionFor(string $id): ServiceDefinition
    {
        if (isset($this->definitions[$id])) {
            return $this->definitions[$id];
        }

        // Zero-Config: Automatically treat unregistered but instantiable
        // classes as transient class definitions.
        if ($this->isInstantiable($id)) {
            /** @var class-string $id */
            return ServiceDefinition::forClass($id, Lifetime::Transient);
        }

        throw NotFoundException::for($id);
    }

    private function build(ServiceDefinition $definition): mixed
    {
        return match ($definition->kind) {
            Kind::Value => $definition->value,
            Kind::Factory => ($definition->factory ?? throw new ContainerException('Factory definition is missing its factory closure.'))($this),
            Kind::Alias => $this->get($definition->alias ?? throw new ContainerException('Alias definition is missing its target id.')),
            Kind::ClassName => $this->autowire($definition->class ?? throw new ContainerException('Class definition is missing its class name.')),
        };
    }

    /**
     * @param  class-string  $class
     *
     * @throws ReflectionException
     */
    private function autowire(string $class): object
    {
        $reflection = $this->reflect($class);

        if (! $reflection->isInstantiable()) {
            throw new ContainerException(sprintf(
                'Cannot instantiate "%s" (interface, abstract class or non-public constructor). '.
                'Register a binding or alias for it.',
                $class,
            ));
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $args = $this->resolveParameters($constructor->getParameters());

        return $reflection->newInstanceArgs($args);
    }

    /**
     * Expands the parameter list of a function or method.
     *
     * @param  list<ReflectionParameter>  $parameters
     * @param  array<string, mixed>  $overrides  Values specified by name.
     * @return list<mixed>
     */
    private function resolveParameters(array $parameters, array $overrides = []): array
    {
        $resolved = [];

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $overrides)) {
                $resolved[] = $overrides[$name];

                continue;
            }

            if ($parameter->isVariadic()) {
                // Variadic parameters are not automatically populated.
                continue;
            }

            $resolved[] = $this->resolveParameter($parameter);
        }

        return $resolved;
    }

    private function resolveParameter(ReflectionParameter $parameter): mixed
    {
        // 1. #[Inject('id')] – explicit service ID
        $injectAttrs = $parameter->getAttributes(Inject::class);
        if ($injectAttrs !== []) {
            return $this->get($injectAttrs[0]->newInstance()->id);
        }

        // 2. #[Config('key')] – Konfigurationswert
        $configAttrs = $parameter->getAttributes(Config::class);
        if ($configAttrs !== []) {
            return $this->resolveConfig($parameter, $configAttrs[0]);
        }

        // 3. Class/Interface Type Hint – Resolve Recursively
        $type = $parameter->getType();
        if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
            $typeName = $type->getName();

            if ($this->has($typeName)) {
                return $this->get($typeName);
            }

            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            if ($type->allowsNull()) {
                return null;
            }

            throw new ContainerException(sprintf(
                'Cannot resolve parameter $%s of type "%s" in %s: not bound and not instantiable.',
                $parameter->getName(),
                $typeName,
                $this->describeFunction($parameter->getDeclaringFunction()),
            ));
        }

        // 4. Default-Value
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        // 5. Nullable
        if ($type !== null && $type->allowsNull()) {
            return null;
        }

        // 6. Cannot be resolved (primitive, union/intersection without a reference, etc.)
        throw new ContainerException(sprintf(
            'Cannot resolve parameter $%s in %s. Provide a #[Inject], #[Config], a default value, or bind it explicitly.',
            $parameter->getName(),
            $this->describeFunction($parameter->getDeclaringFunction()),
        ));
    }

    /**
     * @param  ReflectionAttribute<Config>  $attribute
     */
    private function resolveConfig(ReflectionParameter $parameter, ReflectionAttribute $attribute): mixed
    {
        /** @var Config $config */
        $config = $attribute->newInstance();

        if ($this->config->has($config->key)) {
            return $this->config->get($config->key);
        }

        // Was a default value explicitly passed to the attribute?
        // getArguments() distinguishes between “default: null” and
        // “no default specified”.
        $arguments = $attribute->getArguments();
        $defaultGiven = array_key_exists('default', $arguments) || array_key_exists(1, $arguments);

        if ($defaultGiven) {
            return $config->default;
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw new ContainerException(sprintf(
            'Config key "%s" not found and no default provided for $%s in %s.',
            $config->key,
            $parameter->getName(),
            $this->describeFunction($parameter->getDeclaringFunction()),
        ));
    }

    // ---------------------------------------------------------------------
    // Method / Callable Injection
    // ---------------------------------------------------------------------

    /**
     * Calls a callable and injects its parameters. $parameters overrides
     * resolved values by parameter name.
     *
     * Supports: Closure, "func", "Class::method", [object, "method"],
     * [Class::class, "staticMethod"], invokable objects and class names
     * with __invoke.
     *
     * @param  callable|array{0: object|class-string, 1: string}|string  $callback
     * @param  array<string, mixed>  $parameters
     *
     * @throws ReflectionException
     */
    public function call(callable|array|string $callback, array $parameters = []): mixed
    {
        [$reflection, $invoker] = $this->reflectCallable($callback);
        $args = $this->resolveParameters($reflection->getParameters(), $parameters);

        return $invoker($args);
    }

    /**
     * @param  callable|array{0: object|class-string, 1: string}|string  $callback
     * @return array{0: ReflectionFunctionAbstract, 1: Closure(list<mixed>): mixed}
     *
     * @throws ReflectionException
     */
    private function reflectCallable(callable|array|string $callback): array
    {
        // [objectOrClass, method]
        if (is_array($callback)) {
            [$target, $method] = $callback;

            // [object, method]: bind directly to the given instance.
            if (is_object($target)) {
                $reflection = new ReflectionMethod($target, $method);
                $invoker = static fn (array $a): mixed => $reflection->invokeArgs($target, $a);

                return [$reflection, $invoker];
            }

            // [class-string, method]: resolve the instance unless static.
            $object = new ReflectionMethod($target, $method)->isStatic()
                ? null
                : $this->resolveObject($target);

            $reflection = new ReflectionMethod($object ?? $target, $method);
            $invoker = static fn (array $a): mixed => $reflection->invokeArgs($object, $a);

            return [$reflection, $invoker];
        }

        if ($callback instanceof Closure) {
            $reflection = new ReflectionFunction($callback);

            return [$reflection, static fn (array $a): mixed => $callback(...$a)];
        }

        if (is_string($callback)) {
            if (str_contains($callback, '::')) {
                $reflection = ReflectionMethod::createFromMethodName($callback);
                $object = null;
                if (! $reflection->isStatic()) {
                    [$class] = explode('::', $callback, 2);
                    $object = $this->resolveObject($class);
                }

                return [$reflection, static fn (array $a): mixed => $reflection->invokeArgs($object, $a)];
            }

            if (function_exists($callback)) {
                $reflection = new ReflectionFunction($callback);

                return [$reflection, static fn (array $a): mixed => $callback(...$a)];
            }

            // Invokable class-string
            if (class_exists($callback)) {
                $object = $this->resolveObject($callback);
                $reflection = new ReflectionMethod($object, '__invoke');

                return [$reflection, static fn (array $a): mixed => $reflection->invokeArgs($object, $a)];
            }
        }

        // Invokable Object
        if (is_object($callback)) {
            $reflection = new ReflectionMethod($callback, '__invoke');

            return [$reflection, static fn (array $a): mixed => $callback(...$a)];
        }

        throw new ContainerException('Given value is not a resolvable callable.');
    }

    // ---------------------------------------------------------------------
    // Helper
    // ---------------------------------------------------------------------
    /**
     * Resolves an id that is expected to yield an object (e.g. for method or
     * invokable injection).
     *
     * @param  class-string|string  $id
     */
    private function resolveObject(string $id): object
    {
        $instance = $this->get($id);

        if (! is_object($instance)) {
            throw new ContainerException(sprintf(
                'Expected service "%s" to resolve to an object, got %s.',
                $id,
                get_debug_type($instance),
            ));
        }

        return $instance;
    }

    /**
     * @param  string|class-string  $id
     */
    private function isInstantiable(string $id): bool
    {
        if (! class_exists($id)) {
            return false;
        }

        try {
            return $this->reflect($id)->isInstantiable();
        } catch (ReflectionException) {
            return false;
        }
    }

    /**
     * @param  class-string  $class
     * @return ReflectionClass<object>
     */
    private function reflect(string $class): ReflectionClass
    {
        return $this->reflectionCache[$class] ??= new ReflectionClass($class);
    }

    private function describeFunction(ReflectionFunctionAbstract $function): string
    {
        if ($function instanceof ReflectionMethod) {
            return $function->getDeclaringClass()->getName().'::'.$function->getName().'()';
        }

        return $function->getName().'()';
    }

    public function config(): ConfigResolverInterface
    {
        return $this->config;
    }
}
