<?php
declare(strict_types=1);

namespace App\Queries;

final class PropertiesQuery implements Query
{
    public const PROPS_DELIMITER = '+';

    public function __construct(
        private readonly array $parsedArray,
    ){}

    public static function fromRaw(string $raw) : self
    {
        return new self(self::parseRaw($raw));
    }
    
    public static function fromInput(array $input) : self
    {
        return self::fromRaw( $input['include'] ?? '');
    }

    public function getProps() : array
    {
        return $this->parsedArray;
    }

    public function validateForType(string|object $type, array &$errors) : bool
    {
        if (empty($parsedArray)) return true;

        $className = is_object($type) ? $type::class : $type;

        if (!class_exists($className)) {
            $errors['_type'][] = "Class '$className' does not exist";
            return false;
        }

        $publicProps = self::publicPropertyNames($className);

        $ok = true;

        foreach ($parsedArray as $field) {
            if (!isset($publicProps[$field])) {
                $errors[$field][] = "Unknown property '$field' for $className";
                $ok = false;
            }
        }

        return $ok;
    }

    private static function parseRaw($raw){
        return explode(self::PROPS_DELIMITER, strtolower($raw));
    }

    private static function publicPropertyNames(string $className): array
    {
        $ref = new ReflectionClass($className);
        $names = [];

        foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $names[$prop->getName()] = true;
        }

        return $names;
    }
}