<?php

namespace JTD\FirebaseModels\Tests\Helpers;

/**
 * Field Transform Helpers for FirebaseMock v2
 *
 * Provides helper methods to create field transforms that can be used
 * in tests to simulate Firestore field transforms like serverTimestamp(),
 * increment(), arrayUnion(), etc.
 */
class FieldTransforms
{
    /**
     * Create a server timestamp transform
     */
    public static function serverTimestamp(): array
    {
        return [
            '_transform_type' => 'serverTimestamp',
            '_timestamp' => microtime(true),
        ];
    }

    /**
     * Create an increment transform
     */
    public static function increment(int|float $value = 1): array
    {
        return [
            '_transform_type' => 'increment',
            '_value' => $value,
        ];
    }

    /**
     * Create a decrement transform
     */
    public static function decrement(int|float $value = 1): array
    {
        return [
            '_transform_type' => 'increment',
            '_value' => -$value,
        ];
    }

    /**
     * Create an array union transform
     */
    public static function arrayUnion(array $elements): array
    {
        return [
            '_transform_type' => 'arrayUnion',
            '_elements' => $elements,
        ];
    }

    /**
     * Create an array remove transform
     */
    public static function arrayRemove(array $elements): array
    {
        return [
            '_transform_type' => 'arrayRemove',
            '_elements' => $elements,
        ];
    }

    /**
     * Create a delete field transform
     */
    public static function delete(): array
    {
        return [
            '_transform_type' => 'delete',
        ];
    }

    /**
     * Apply field transforms to existing data
     */
    public static function applyTransforms(array $existingData, array $transforms): array
    {
        $result = $existingData;

        foreach ($transforms as $field => $transform) {
            if (!is_array($transform) || !isset($transform['_transform_type'])) {
                $result[$field] = $transform;

                continue;
            }

            $result[$field] = self::applyTransform($result[$field] ?? null, $transform);
        }

        return $result;
    }

    /**
     * Apply a single transform to a field value
     */
    protected static function applyTransform($currentValue, array $transform): mixed
    {
        return match ($transform['_transform_type']) {
            'serverTimestamp' => new \DateTime(),
            'increment' => ($currentValue ?? 0) + ($transform['_value'] ?? 1),
            'arrayUnion' => self::applyArrayUnion($currentValue, $transform['_elements'] ?? []),
            'arrayRemove' => self::applyArrayRemove($currentValue, $transform['_elements'] ?? []),
            'delete' => null,
            default => $transform,
        };
    }

    /**
     * Apply array union operation
     */
    protected static function applyArrayUnion($currentValue, array $elements): array
    {
        $current = is_array($currentValue) ? $currentValue : [];

        foreach ($elements as $element) {
            if (!in_array($element, $current, true)) {
                $current[] = $element;
            }
        }

        return $current;
    }

    /**
     * Apply array remove operation
     */
    protected static function applyArrayRemove($currentValue, array $elements): array
    {
        if (!is_array($currentValue)) {
            return [];
        }

        return array_values(array_filter($currentValue, function ($item) use ($elements) {
            return !in_array($item, $elements, true);
        }));
    }

    /**
     * Check if a value contains field transforms
     */
    public static function hasTransforms(array $data): bool
    {
        foreach ($data as $value) {
            if (is_array($value) && isset($value['_transform_type'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract field transforms from data
     */
    public static function extractTransforms(array $data): array
    {
        $transforms = [];

        foreach ($data as $field => $value) {
            if (is_array($value) && isset($value['_transform_type'])) {
                $transforms[$field] = $value;
            }
        }

        return $transforms;
    }

    /**
     * Remove field transforms from data (get plain values)
     */
    public static function removeTransforms(array $data): array
    {
        $plain = [];

        foreach ($data as $field => $value) {
            if (is_array($value) && isset($value['_transform_type'])) {
                // Skip transforms, they'll be applied separately
                continue;
            }
            $plain[$field] = $value;
        }

        return $plain;
    }

    /**
     * Create mock field value helpers for testing
     */
    public static function mockFieldValue(): object
    {
        return new class()
        {
            public function serverTimestamp(): array
            {
                return FieldTransforms::serverTimestamp();
            }

            public function increment(int|float $value = 1): array
            {
                return FieldTransforms::increment($value);
            }

            public function arrayUnion(array $elements): array
            {
                return FieldTransforms::arrayUnion($elements);
            }

            public function arrayRemove(array $elements): array
            {
                return FieldTransforms::arrayRemove($elements);
            }

            public function delete(): array
            {
                return FieldTransforms::delete();
            }
        };
    }
}
