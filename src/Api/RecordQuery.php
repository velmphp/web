<?php

declare(strict_types=1);

namespace Velm\Web\Api;

use Velm\Environment;

final class RecordQuery
{
    public function __construct(
        private readonly RecordSerializer $serializer = new RecordSerializer,
    ) {}

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
        if (! $env->registry->has($model)) {
            throw ModelNotFoundException::forModel($model);
        }

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
