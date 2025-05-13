<?php

declare(strict_types=1);

namespace TimAlexander\Myagent\Data;

abstract class DataModel
{
    /**
     * This returns all protected properties of the inheriting class.
     */
    public function toArray(): array
    {
        $objectVars = get_object_vars($this);

        unset($objectVars['unset']);

        return self::turnIntoArrayRecursive($objectVars);
    }

    private static function turnIntoArrayRecursive(array $data): array
    {
        $array = [];
        foreach ($data as $key => $value) {
            if ($value instanceof self) {
                $array[$key] = $value->toArray();
                continue;
            }
            if (is_array($value)) {
                $array[$key] = self::turnIntoArrayRecursive($value);
                continue;
            }
            $array[$key] = $value;
        }
        return $array;
    }

    public static function getPropertyNames(): array
    {
        $reflection = new \ReflectionClass(static::class);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PROTECTED);

        $propertyNames = [];
        foreach ($properties as $property) {
            if ($property->isPrivate()) {
                continue;
            }

            $propertyNames[] = $property->getName();
        }

        return $propertyNames;
    }
}
