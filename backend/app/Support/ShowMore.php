<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;

/**
 * Lists that grow with "Show more" instead of page numbers. The page asks for
 * the next slice (?offset=&limit=): 10 first, then 10 more, then 20 at a time;
 * the answer says whether anything is left, so the page can say "all caught up".
 */
final class ShowMore
{
    public const FIRST = 10;

    public const MAX = 50;

    /** @return array{0: int, 1: int} offset and limit from the request */
    public static function window(Request $request, int $first = self::FIRST): array
    {
        $offset = max(0, (int) $request->input('offset', 0));
        $limit = (int) $request->input('limit', $first);

        return [$offset, min(max($limit, 1), self::MAX)];
    }

    /**
     * One slice of an ordered query, and whether more rows follow.
     *
     * @return array{items: \Illuminate\Support\Collection, meta: array{offset: int, limit: int, has_more: bool, total: int}}
     */
    public static function slice($query, Request $request, int $first = self::FIRST): array
    {
        [$offset, $limit] = self::window($request, $first);
        $total = (clone $query)->toBase()->getCountForPagination();
        $items = (clone $query)->offset($offset)->limit($limit)->get();

        return ['items' => $items, 'meta' => self::meta($offset, $limit, $total)];
    }

    public static function meta(int $offset, int $limit, int $total): array
    {
        return ['offset' => $offset, 'limit' => $limit, 'has_more' => $offset + $limit < $total, 'total' => $total];
    }
}
