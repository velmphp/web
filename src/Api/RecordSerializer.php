<?php

declare(strict_types=1);

namespace Velm\Web\Api;

use Velm\Environment;
use Velm\Fields\Field;
use Velm\Fields\Many2oneField;
use Velm\Models\Model;

final class RecordSerializer
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>|null  $fieldNames
     * @return list<array<string, mixed>>
     */
    public function serialize(
        Environment $env,
        string $model,
        array $rows,
        ?array $fieldNames = null,
    ): array {
        $modelClass = $env->registry->modelClass($model);
        $fields = $modelClass::fields();

        return array_map(
            fn (array $row): array => $this->serializeRow($env, $modelClass, $fields, $row, $fieldNames),
            $rows,
        );
    }

    /**
     * @param  array<string, Field>  $fields
     * @param  array<string, mixed>  $row
     * @param  list<string>|null  $fieldNames
     * @return array<string, mixed>
     */
    private function serializeRow(
        Environment $env,
        string $modelClass,
        array $fields,
        array $row,
        ?array $fieldNames,
    ): array {
        $out = ['id' => $row['id']];

        $names = $fieldNames ?? array_keys(array_filter(
            $fields,
            static fn (string $name): bool => $name !== 'display_name',
            ARRAY_FILTER_USE_KEY,
        ));

        foreach ($names as $name) {
            if ($name === 'id') {
                continue;
            }

            if ($name === 'display_name') {
                $out['display_name'] = $row['display_name'] ?? $modelClass::displayNameFor($row);

                continue;
            }

            if (! isset($fields[$name])) {
                throw new \InvalidArgumentException("Unknown field {$name} on {$modelClass::name()}.");
            }

            $field = $fields[$name];
            $value = $row[$name] ?? null;

            if ($field instanceof Many2oneField) {
                $out[$name] = $this->serializeMany2one($env, $field, $value);

                continue;
            }

            $out[$name] = $value;
        }

        return $out;
    }

    /**
     * @return null|list{int, string}
     */
    private function serializeMany2one(Environment $env, Many2oneField $field, mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        $id = (int) $value;
        $related = $env->browse($field->comodel, [$id])->read();

        if ($related === []) {
            return [$id, (string) $id];
        }

        return [$id, (string) $related[0]['display_name']];
    }
}
