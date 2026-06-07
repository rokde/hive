<?php

declare(strict_types=1);

namespace Hive\DependencyInjection;

use Closure;

final readonly class ServiceDefinition
{
    /**
     * @param  Kind  $kind  Kind of the definition
     * @param  class-string|null  $class  For Kind::ClassName / Kind::Alias: target class name
     * @param  Closure|null  $factory  For Kind::Factory: the factory (fn(Container): mixed)
     * @param  mixed  $value  For Kind::Value: the (static) value
     * @param  string|null  $alias  For Kind::Alias: target class name to delegate to
     */
    private function __construct(
        public Kind $kind,
        public Lifetime $lifetime,
        public bool $eager,
        public ?string $class = null,
        public ?Closure $factory = null,
        public mixed $value = null,
        public ?string $alias = null,
    ) {}

    /**
     * Service with Auto-Wiring of a specific class.
     *
     * @param  class-string  $class
     */
    public static function forClass(
        string $class,
        Lifetime $lifetime = Lifetime::Transient,
        bool $eager = false,
    ): self {
        return new self(Kind::ClassName, $lifetime, $eager, class: $class);
    }

    /**
     * Service for a factory-Closure. Gets the Container as argument.
     */
    public static function forFactory(
        Closure $factory,
        Lifetime $lifetime = Lifetime::Transient,
        bool $eager = false,
    ): self {
        return new self(Kind::Factory, $lifetime, $eager, factory: $factory);
    }

    /**
     * An already existent value / an instance. Always shared (singleton-semanic).
     */
    public static function forValue(mixed $value): self
    {
        return new self(Kind::Value, Lifetime::Singleton, eager: true, value: $value);
    }

    /**
     * Alias: Delegates the resolving to another id (e.g. Interface -> Implementation).
     * The lifecycale is controlled by the target service.
     */
    public static function forAlias(string $targetId): self
    {
        return new self(Kind::Alias, Lifetime::Transient, eager: false, alias: $targetId);
    }

    public function withLifetime(Lifetime $lifetime): self
    {
        return new self($this->kind, $lifetime, $this->eager, $this->class, $this->factory, $this->value, $this->alias);
    }

    public function asEager(bool $eager = true): self
    {
        return new self($this->kind, $this->lifetime, $eager, $this->class, $this->factory, $this->value, $this->alias);
    }

    public function isShared(): bool
    {
        return $this->lifetime === Lifetime::Singleton;
    }
}
