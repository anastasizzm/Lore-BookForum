<?php
declare(strict_types=1);

namespace App\Lib;

use Closure;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

final class Container
{
    private const string KEY_IDENTIFIER = '__KEY__ID__';

    /** @var array<string, Closure> */
    private array $factories = [];

    /** @var array<string, object> */
    private array $instances = [];

    /** Register a factory (lazy, one instance per container). */
    public function singleton(string $id, ?Closure $factory = null): void
    {
        $this->factories[$id] = $factory ?? static fn(self $c) => new $id();
    }

    public function signletonKeyed(string $key, string $id, ?Closure $factory = NULL) : void 
    {
        $savedId = $this->generateKey($key, $id);
        $this->factories[$savedId] = $factory ?? static fn(self $c, $key) => new $id();
    }

    /** Register a pre-built instance. */
    public function instance(string $id, object $value): void
    {
        $this->instances[$id] = $value;
    }

    public function instanceKeyed(string $key, string $id, object $value) : void 
    {
        $this->instance($this->generateKey($key, $id), $value);
    }

    public function getKeyed(string $key, string $id) : object 
    {
        $savedId = $this->generateKey($key, $id);

        if (isset($this->instances[$savedId])) {
            return $this->instances[$savedId];
        }

        if (isset($this->factories[$savedId])) {
            return $this->instances[$savedId] = ($this->factories[$savedId])($this, $key);
        }

        throw new RuntimeException("The keyed '$key' service of id '$id' is not set");
    }

    private function generateKey(string $key, string $id) : string { return $id . self::KEY_IDENTIFIER . $key; }

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
            $type = $param->getType();

            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                if ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();
                    continue;
                }
                throw new RuntimeException(
                    "Cannot auto-wire \${$param->getName()} in $class"
                );
            }

            $args[] = $this->get($type->getName());
        }

        return $ref->newInstanceArgs($args);
    }
}