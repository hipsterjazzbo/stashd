<?php

declare(strict_types=1);

namespace App\Auth;

use Tempest\Mapper\SerializeAs;

#[SerializeAs('api_token_scopes')]
final readonly class ApiTokenScopes
{
    /** @param list<string> $values */
    public function __construct(
        public array $values = [],
    ) {
    }

    /** @param array<mixed>|null $values */
    public static function fromArray(?array $values): self
    {
        if ($values === null) {
            return new self();
        }

        $scopes = [];

        foreach ($values as $value) {
            if (! is_string($value)) {
                continue;
            }

            $scope = trim($value);

            if ($scope === '') {
                continue;
            }

            $scopes[$scope] = $scope;
        }

        return new self(array_values($scopes));
    }

    /** @return list<string> */
    public function toArray(): array
    {
        return $this->values;
    }

}
