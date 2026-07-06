<?php

declare(strict_types=1);

namespace App\Support\Http;

use Tempest\Http\Request;

final class QueryPagination
{
    /** @return array{0: int, 1: int} [limit, offset] */
    public static function parse(Request $request, int $defaultLimit = 50, int $maxLimit = 200): array
    {
        $rawLimit = $request->get('limit');
        $rawOffset = $request->get('offset');

        $limit = is_numeric($rawLimit) ? (int) $rawLimit : $defaultLimit;
        $offset = is_numeric($rawOffset) ? (int) $rawOffset : 0;

        return [max(1, min($maxLimit, $limit)), max(0, $offset)];
    }
}
