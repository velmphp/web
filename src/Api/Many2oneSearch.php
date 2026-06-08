<?php

declare(strict_types=1);

namespace Velm\Web\Api;

use Velm\Environment;
use Velm\Fields\CharField;
use Velm\Fields\Field;
use Velm\Fields\TextField;

final class Many2oneSearch
{
    /**
     * @return array{results: list<array{id: int, label: string}>}
     */
    public function search(
        Environment $env,
        string $model,
        string $query,
        int $limit = 10,
    ): array {
        if (! $env->registry->has($model)) {
            throw ModelNotFoundException::forModel($model);
        }

        $fields = $env->registry->fieldSet($model);
        $domain = $env->registry->modelClass($model)::relationalSearchDomain();
        $textField = $this->resolveTextField($fields);

        if ($query !== '' && $textField !== null) {
            $domain[] = [$textField, 'ilike', '%'.$query.'%'];
        }

        $recordset = $env->model($model)->search($domain, $limit, 0, '"id" ASC');
        $rows = $recordset->read();

        $results = array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'label' => (string) ($row['display_name'] ?? $row['id']),
            ],
            $rows,
        );

        return ['results' => $results];
    }

    /**
     * @param  array<string, Field>  $fields
     */
    private function resolveTextField(array $fields): ?string
    {
        if (isset($fields['name']) && $this->isTextLike($fields['name'])) {
            return 'name';
        }

        foreach ($fields as $name => $field) {
            if ($name === 'id' || $name === 'display_name') {
                continue;
            }

            if ($this->isTextLike($field)) {
                return $name;
            }
        }

        return null;
    }

    private function isTextLike(Field $field): bool
    {
        return $field instanceof CharField || $field instanceof TextField;
    }
}
