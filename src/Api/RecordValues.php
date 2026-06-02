<?php

declare(strict_types=1);

namespace Velm\Web\Api;

use Velm\Environment;
use Velm\Fields\Many2oneField;

final class RecordValues
{
    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function coerce(Environment $env, string $model, array $values): array
    {
        $modelClass = $env->registry->modelClass($model);
        $fields = $modelClass::fields();
        $unknown = [];
        $coerced = [];

        foreach ($values as $name => $value) {
            if ($name === 'id' || $name === 'display_name') {
                throw new \InvalidArgumentException("Cannot set {$name} via API.");
            }

            if (! isset($fields[$name])) {
                $unknown[] = $name;

                continue;
            }

            $coerced[$name] = $this->coerceValue($fields[$name], $value);
        }

        if ($unknown !== []) {
            throw new \InvalidArgumentException(
                'Unknown field(s) on '.$model.': '.implode(', ', $unknown),
            );
        }

        return $coerced;
    }

    private function coerceValue(\Velm\Fields\Field $field, mixed $value): mixed
    {
        if ($field instanceof Many2oneField) {
            return $this->coerceMany2one($value);
        }

        return $value;
    }

    private function coerceMany2one(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            if ($value === []) {
                return null;
            }

            return (int) $value[0];
        }

        return (int) $value;
    }
}
