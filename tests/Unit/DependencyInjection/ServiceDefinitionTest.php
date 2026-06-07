<?php

declare(strict_types=1);

use Hive\DependencyInjection\Kind;
use Hive\DependencyInjection\Lifetime;
use Hive\DependencyInjection\ServiceDefinition;

test('forClass builds a class definition with defaults', function (): void {
    $definition = ServiceDefinition::forClass(stdClass::class);

    expect($definition->kind)->toBe(Kind::ClassName)
        ->and($definition->class)->toBe(stdClass::class)
        ->and($definition->lifetime)->toBe(Lifetime::Transient)
        ->and($definition->eager)->toBeFalse()
        ->and($definition->factory)->toBeNull()
        ->and($definition->value)->toBeNull()
        ->and($definition->alias)->toBeNull();
});

test('forClass accepts custom lifetime and eager', function (): void {
    $definition = ServiceDefinition::forClass(stdClass::class, Lifetime::Singleton, true);

    expect($definition->lifetime)->toBe(Lifetime::Singleton)
        ->and($definition->eager)->toBeTrue();
});

test('forFactory builds a factory definition with defaults', function (): void {
    $factory = fn (): stdClass => new stdClass;

    $definition = ServiceDefinition::forFactory($factory);

    expect($definition->kind)->toBe(Kind::Factory)
        ->and($definition->factory)->toBe($factory)
        ->and($definition->lifetime)->toBe(Lifetime::Transient)
        ->and($definition->eager)->toBeFalse()
        ->and($definition->class)->toBeNull();
});

test('forFactory accepts custom lifetime and eager', function (): void {
    $definition = ServiceDefinition::forFactory(fn (): null => null, Lifetime::Singleton, true);

    expect($definition->lifetime)->toBe(Lifetime::Singleton)
        ->and($definition->eager)->toBeTrue();
});

test('forValue is always a shared eager value definition', function (): void {
    $value = new stdClass;

    $definition = ServiceDefinition::forValue($value);

    expect($definition->kind)->toBe(Kind::Value)
        ->and($definition->value)->toBe($value)
        ->and($definition->lifetime)->toBe(Lifetime::Singleton)
        ->and($definition->eager)->toBeTrue()
        ->and($definition->isShared())->toBeTrue();
});

test('forValue keeps scalar values', function (): void {
    expect(ServiceDefinition::forValue(42)->value)->toBe(42)
        ->and(ServiceDefinition::forValue('foo')->value)->toBe('foo')
        ->and(ServiceDefinition::forValue(null)->value)->toBeNull();
});

test('forAlias builds a transient non-eager alias definition', function (): void {
    $definition = ServiceDefinition::forAlias('some.target.id');

    expect($definition->kind)->toBe(Kind::Alias)
        ->and($definition->alias)->toBe('some.target.id')
        ->and($definition->lifetime)->toBe(Lifetime::Transient)
        ->and($definition->eager)->toBeFalse();
});

test('withLifetime returns a new instance with the changed lifetime', function (): void {
    $original = ServiceDefinition::forClass(stdClass::class);

    $changed = $original->withLifetime(Lifetime::Singleton);

    expect($changed)->not->toBe($original)
        ->and($changed->lifetime)->toBe(Lifetime::Singleton)
        ->and($original->lifetime)->toBe(Lifetime::Transient)
        ->and($changed->kind)->toBe($original->kind)
        ->and($changed->class)->toBe($original->class);
});

test('asEager defaults to true and returns a new instance', function (): void {
    $original = ServiceDefinition::forClass(stdClass::class);

    $changed = $original->asEager();

    expect($changed)->not->toBe($original)
        ->and($changed->eager)->toBeTrue()
        ->and($original->eager)->toBeFalse();
});

test('asEager can disable eager', function (): void {
    $definition = ServiceDefinition::forClass(stdClass::class, eager: true)->asEager(false);

    expect($definition->eager)->toBeFalse();
});

test('withLifetime and asEager preserve all other fields', function (): void {
    $factory = fn (): null => null;

    $definition = ServiceDefinition::forFactory($factory)
        ->withLifetime(Lifetime::Singleton)
        ->asEager();

    expect($definition->kind)->toBe(Kind::Factory)
        ->and($definition->factory)->toBe($factory)
        ->and($definition->lifetime)->toBe(Lifetime::Singleton)
        ->and($definition->eager)->toBeTrue();
});

test('isShared is true only for singleton lifetime', function (): void {
    expect(ServiceDefinition::forClass(stdClass::class, Lifetime::Singleton)->isShared())->toBeTrue()
        ->and(ServiceDefinition::forClass(stdClass::class, Lifetime::Transient)->isShared())->toBeFalse();
});
