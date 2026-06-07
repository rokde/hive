<?php

declare(strict_types=1);

namespace Hive\Config\Resolver;

final class ArrayConfigResolver implements ConfigResolverInterface
{
    /**
     * @param  array<string, mixed>  $items
     */
    public function __construct(
        private array $items = [],
    ) {}

    public function has(string $key): bool
    {
        $sentinel = "\0__missing__\0";

        return $this->get($key, $sentinel) !== $sentinel;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->items)) {
            return $this->items[$key];
        }

        $value = $this->items;

        foreach (explode('.', $key) as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];

                continue;
            }

            return $default;
        }

        return $value;
    }

    /**
     * @param  string|class-string  $key
     */
    public function set(string $key, mixed $value): void
    {
        $this->items[$key] = $value;
    }
}
