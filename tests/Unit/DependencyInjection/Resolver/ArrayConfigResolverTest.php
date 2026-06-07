<?php

declare(strict_types=1);

use Hive\DependencyInjection\Resolver\ArrayConfigResolver;
use Hive\DependencyInjection\Resolver\ConfigResolverInterface;

test('implements the config resolver interface', function (): void {
    expect(new ArrayConfigResolver)->toBeInstanceOf(ConfigResolverInterface::class);
});

test('get returns a flat value', function (): void {
    $resolver = new ArrayConfigResolver(['app' => 'hive']);

    expect($resolver->get('app'))->toBe('hive');
});

test('get returns the default when key is missing', function (): void {
    $resolver = new ArrayConfigResolver;

    expect($resolver->get('missing'))->toBeNull()
        ->and($resolver->get('missing', 'fallback'))->toBe('fallback');
});

test('get resolves nested keys via dot notation', function (): void {
    $resolver = new ArrayConfigResolver([
        'database' => [
            'connection' => [
                'host' => 'localhost',
            ],
        ],
    ]);

    expect($resolver->get('database.connection.host'))->toBe('localhost')
        ->and($resolver->get('database.connection'))->toBe(['host' => 'localhost']);
});

test('get returns the default for a partially missing dot path', function (): void {
    $resolver = new ArrayConfigResolver(['database' => ['host' => 'localhost']]);

    expect($resolver->get('database.port', 5432))->toBe(5432)
        ->and($resolver->get('database.host.deep'))->toBeNull();
});

test('get prefers a flat key over dot traversal', function (): void {
    $resolver = new ArrayConfigResolver([
        'a.b' => 'flat',
        'a' => ['b' => 'nested'],
    ]);

    expect($resolver->get('a.b'))->toBe('flat');
});

test('get returns a stored null value rather than the default', function (): void {
    $resolver = new ArrayConfigResolver(['nullable' => null]);

    expect($resolver->get('nullable', 'fallback'))->toBeNull();
});

test('has is true for existing flat and nested keys', function (): void {
    $resolver = new ArrayConfigResolver([
        'app' => 'hive',
        'database' => ['host' => 'localhost'],
    ]);

    expect($resolver->has('app'))->toBeTrue()
        ->and($resolver->has('database.host'))->toBeTrue();
});

test('has is false for missing keys', function (): void {
    $resolver = new ArrayConfigResolver(['app' => 'hive']);

    expect($resolver->has('missing'))->toBeFalse()
        ->and($resolver->has('app.deep'))->toBeFalse();
});

test('has is true for a key holding null', function (): void {
    $resolver = new ArrayConfigResolver(['nullable' => null]);

    expect($resolver->has('nullable'))->toBeTrue();
});

test('set stores a value retrievable via get', function (): void {
    $resolver = new ArrayConfigResolver;

    $resolver->set('app', 'hive');

    expect($resolver->get('app'))->toBe('hive')
        ->and($resolver->has('app'))->toBeTrue();
});

test('set overwrites an existing value', function (): void {
    $resolver = new ArrayConfigResolver(['app' => 'old']);

    $resolver->set('app', 'new');

    expect($resolver->get('app'))->toBe('new');
});
