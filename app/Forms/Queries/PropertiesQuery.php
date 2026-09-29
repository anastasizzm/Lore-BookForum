<?php
declare(strict_types=1);

namespace App\Forms\Queries;

use App\Forms\Form;

final class PropertiesQuery implements Form
{
    public const PROPS_DELIMITER = '+';

    protected function __construct(
        private readonly array $parsedArray,
        private string|object|null $type
    ){}
    
    public static function fromRaw(string|object $type, string $rawProps) : self
    {
        return new self(self::parseRaw($rawProps), $type);
    }

    public static function fromInput(array $input) : self
    {
        $raw = $input['include'] ?? '';
        return new self(self::parseRaw($raw));
    }

    public function setType(string|object $type){
        $this->type = $type;
    }

    public function getType() : string|object|null
    {
        return $this->type;
    }

    public function getProps() : array
    {
        return $this->parsedArray;
    }

    public function validate(array &$errors) : bool
    {
        if (empty($parsedArray)) return true;

        $className = is_object($this->type) ? $this->type::class : $this->type;

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