<?php
declare(strict_types=1);

namespace App\Extensions;

final class EnumExtensions
{
    public static function tryResolve(string $enumClass, string $value): ?UnitEnum
    {
        if (!enum_exists($enumClass)) {
            return null;
        }

        // 1. Backed enum — попытка через from() по value
        if (is_subclass_of($enumClass, BackedEnum::class)) {
            /** @var class-string<BackedEnum> $enumClass */
            try {
                return $enumClass::from($value);
            } catch (ValueError) {}
        }

        // 2. Поиск по имени case, без учёта регистра
        $ref = new ReflectionEnum($enumClass);

        foreach ($ref->getCases() as $case) {
            if (strcasecmp($case->getName(), $value) === 0) {
                return $case->getValue();
            }
        }

        return null;
    }
}