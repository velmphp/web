<?php

declare(strict_types=1);

namespace Velm\Web\Api;

use Velm\Environment;

final class RecordQuery
{
    public function __construct(
        private readonly RecordSerializer $serializer = new RecordSerializer,
        private readonly RecordValues $values = new RecordValues,
    ) {}

    public function assertModel(Environment $env, string $model): void
    {
        if (! $env->registry->has($model)) {
            throw ModelNotFoundException::forModel($model);
        }
    }

    public function assertExists(Environment $env, string $model, int $id): void
    {
        $exists = $env->model($model)->search([['id', '=', $id]])->count() > 0;

        if (! $exists) {
            throw RecordNotFoundException::forRecord($model, $id);
        }
    }

    /**
     * @param  list<mixed>|list<list<mixed>>  $domain
     * @param  list<string>|null  $fields
     * @return array{model: string, count: int, records: list<array<string, mixed>>}
     */
    public function list(
        Environment $env,
        string $model,
        array $domain = [],
        ?array $fields = null,
        int $limit = 0,
        int $offset = 0,
        ?string $order = null,
    ): array {
        $this->assertModel($env, $model);

        if ($fields !== null) {
            $this->assertKnownFields($env, $model, $fields);
        }

        $recordset = $env->model($model)->search($domain, $limit, $offset, $order);
        $rows = $recordset->read($fields);

        return [
            'model' => $model,
            'count' => count($rows),
            'records' => $this->serializer->serialize($env, $model, $rows, $fields),
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function create(Environment $env, string $model, array $values): array
    {
        $this->assertModel($env, $model);
        $coerced = $this->values->coerce($env, $model, $values);
        $recordset = $env->model($model)->create($coerced);
        $row = $recordset->read()[0];

        return $this->serializer->serializeOne($env, $model, $row);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function write(Environment $env, string $model, int $id, array $values): array
    {
        $this->assertModel($env, $model);
        $this->assertExists($env, $model, $id);
        $coerced = $this->values->coerce($env, $model, $values);
        $recordset = $env->browse($model, [$id]);
        $recordset->write($coerced);
        $row = $recordset->read()[0];

        return $this->serializer->serializeOne($env, $model, $row);
    }

    public function delete(Environment $env, string $model, int $id): void
    {
        $this->assertModel($env, $model);
        $this->assertExists($env, $model, $id);
        $env->browse($model, [$id])->unlink();
    }

    /**
     * @param  list<string>  $fields
     */
    private function assertKnownFields(Environment $env, string $model, array $fields): void
    {
        $modelClass = $env->registry->modelClass($model);
        $allowed = $modelClass::fields();

        foreach ($fields as $name) {
            if ($name === 'id' || $name === 'display_name') {
                continue;
            }

            if (! isset($allowed[$name])) {
                throw new \InvalidArgumentException("Unknown field {$name} on {$model}.");
            }
        }
    }
}
