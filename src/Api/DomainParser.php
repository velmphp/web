<?php

declare(strict_types=1);

namespace Velm\Web\Api;

final class DomainParser
{
    /**
     * @return list<mixed>|list<list<mixed>>
     */
    public function parse(string $domainJson): array
    {
        if ($domainJson === '' || $domainJson === '[]') {
            return [];
        }

        try {
            $domain = json_decode($domainJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw InvalidDomainException::forMessage('Invalid domain JSON: '.$exception->getMessage());
        }

        if (! is_array($domain)) {
            throw InvalidDomainException::forMessage('Domain must be a JSON array.');
        }

        return $domain;
    }
}
