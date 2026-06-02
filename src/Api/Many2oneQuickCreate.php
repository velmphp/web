<?php

declare(strict_types=1);

namespace Velm\Web\Api;

use Velm\Environment;
use Velm\Fields\Field;
use Velm\Models\Model;

final class Many2oneQuickCreate
{
    /**
     * @return array{id: int, label: string}
     */
    public function create(Environment $env, string $model, string $name): array
    {
        if (! $env->registry->has($model)) {
            throw ModelNotFoundException::forModel($model);
        }

        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException("Missing 'name'.");
        }

        $modelClass = $env->registry->modelClass($model);
        $fields = $modelClass::fields();

        if (! isset($fields['name'])) {
            throw new \InvalidArgumentException("Model {$model} has no name field for quick-create.");
        }

        $this->assertQuickCreatable($model, $fields);

        $recordset = $env->model($model)->create(['name' => $name]);
        $row = $recordset->read()[0];

        return [
            'id' => (int) $row['id'],
            'label' => (string) ($row['display_name'] ?? $name),
        ];
    }

    /**
     * @param  array<string, Field>  $fields
     */
    public function assertQuickCreatable(string $model, array $fields): void
    {
        $missing = [];

        foreach ($fields as $fieldName => $field) {
            if (! $field->required || $fieldName === 'name') {
                continue;
            }

            if ($field->default !== null) {
                continue;
            }

            $missing[] = $fieldName;
        }

        if ($missing !== []) {
            throw CannotQuickCreateException::forModel($model, $missing);
        }
    }

    /**
     * Whether the comodel can be created with only a display name.
     *
     * @param  class-string<Model>  $modelClass
     */
    public function canQuickCreate(string $modelClass): bool
    {
        try {
            $this->assertQuickCreatable($modelClass::name(), $modelClass::fields());

            return isset($modelClass::fields()['name']);
        } catch (\InvalidArgumentException) {
            return false;
        }
    }
}
