<?php
declare(strict_types=1);

namespace App\Lib;

use App\Attributes\Inject;
use Closure;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;

use BackedEnum;
use UnitEnum;
use Stringable;

final class Container
{
    private const string KEY_IDENTIFIER = '__KEY__ID__';

    /** @var array<string, Closure> */
    private array $factories = [];

    /** @var array<string, object> */
    private array $instances = [];

    public function singleton(string $id, ?Closure $factory = null): void
    {
        $this->factories[$id] = $factory ?? static fn(self $c) => new $id();
    }

    public function singletonKeyed(mixed $key, string $id, ?Closure $factory = null): void
    {
        $savedId = $this->generateKey($key, $id);
        $this->factories[$savedId] = $factory ?? static fn(self $c, mixed $key) => new $id();
    }

    public function instance(string $id, object $value): void
    {
        $this->instances[$id] = $value;
    }

    public function instanceKeyed(mixed $key, string $id, object $value): void
    {
        $this->instance($this->generateKey($key, $id), $value);
    }

    public function getKeyed(mixed $key, string $id): object
    {
        $savedId = $this->generateKey($key, $id);

        if (isset($this->instances[$savedId])) {
            return $this->instances[$savedId];
        }

        if (isset($this->factories[$savedId])) {
            return $this->instances[$savedId] = ($this->factories[$savedId])($this, $key);
        }

        throw new RuntimeException("The keyed '{$this->normalizeKey($key)}' service of id '$id' is not set");
    }

    private function generateKey(mixed $key, string $id): string
    {
        return $id . self::KEY_IDENTIFIER . self::normalizeKey($key);
    }

    private static function normalizeKey(mixed $key): string
    {
        return match (true) {
            is_string($key)            => $key,
            is_int($key)               => (string) $key,
            is_float($key)             => (string) $key,
            is_bool($key)              => $key ? '1' : '0',
            $key instanceof BackedEnum => (string) $key->value,
            $key instanceof UnitEnum   => $key->name,
            $key instanceof Stringable => (string) $key,
            $key === null              => 'null',
            default => throw new InvalidArgumentException(
                'Container key must be string, int, float, bool, null, '
                . 'an enum case, or Stringable; got '
                . get_debug_type($key)
            ),
        };
    }

    /** Resolve a class / service id, building it on first use. */
    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (isset($this->factories[$id])) {
            return $this->instances[$id] = ($this->factories[$id])($this);
        }

        // Auto-wire: build via reflection if not registered.
        return $this->instances[$id] = $this->build($id);
    }

    private function build(string $class): object
    {
        if (!class_exists($class)) {
            throw new RuntimeException("Cannot resolve: $class");
        }

        $ref = new ReflectionClass($class);

        if (!$ref->isInstantiable()) {
            throw new RuntimeException("Not instantiable: $class");
        }

        $ctor = $ref->getConstructor();

        if ($ctor === null || $ctor->getNumberOfParameters() === 0) {
            return $ref->newInstance();
        }

        $args = [];
        foreach ($ctor->getParameters() as $param) {
            $args[] = $this->resolveParameter($param, $class);
        }

        return $ref->newInstanceArgs($args);
    }

    private function resolveParameter(ReflectionParameter $param, string $class): mixed
    {
        $attr   = $param->getAttributes(Inject::class)[0] ?? null;
        $inject = $attr?->newInstance();

        $type     = $param->getType();
        $typeName = $type instanceof ReflectionNamedType && !$type->isBuiltin()
            ? $type->getName()
            : null;

        // 1) Explicit #[Inject] attribute wins.
        if ($inject !== null) {
            $id = $inject->id ?? $typeName;

            if ($id === null) {
                throw new RuntimeException(
                    "Cannot resolve \${$param->getName()} in $class: " .
                    "#[Inject] requires either a class type or an explicit id"
                );
            }

            return $inject->key !== null
                ? $this->getKeyed($inject->key, $id)
                : $this->get($id);
        }

        // 2) No attribute: fall back to type-based auto-wiring.
        if ($typeName !== null) {
            return $this->get($typeName);
        }

        // 3) Scalar / untyped parameter — only resolvable via default value.
        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        throw new RuntimeException(
            "Cannot auto-wire \${$param->getName()} in $class"
        );
    }
}