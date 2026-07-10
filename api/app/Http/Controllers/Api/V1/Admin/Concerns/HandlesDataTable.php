<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * بحثٌ وفرزٌ وترقيمٌ مشترك لجداول لوحة المشرف.
 *
 * ⚠️ الفرز يُقيَّد بقائمة أعمدة مسموحة (allowlist) لا بمدخلات المستخدم.
 *    `?sort=password` أو `?sort=users.remember_token` تسريبٌ عبر الترتيب.
 */
trait HandlesDataTable
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $sortable  أعمدة الفرز المسموحة
     * @return LengthAwarePaginator<int, TModel>
     */
    protected function paginateTable(Request $request, Builder $query, array $sortable, string $defaultSort): LengthAwarePaginator
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', 'string', 'max:60'],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $sort = (string) $request->string('sort');
        $column = in_array($sort, $sortable, true) ? $sort : $defaultSort;
        $direction = $request->string('direction')->value() === 'asc' ? 'asc' : 'desc';

        return $query
            ->orderBy($column, $direction)
            ->paginate((int) $request->integer('per_page', 15))
            ->withQueryString();
    }

    /**
     * @param  LengthAwarePaginator<int, covariant Model>  $paginator
     * @return array<string, int>
     */
    protected function tableMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
        ];
    }

    /**
     * معرّفات الحذف الجماعي — مصفوفة أعداد صحيحة، بحدٍّ أعلى.
     *
     * @return list<int>
     */
    protected function bulkIds(Request $request, string $table): array
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer', "exists:{$table},id"],
        ]);

        /** @var list<int> $ids */
        $ids = array_map(intval(...), $data['ids']);

        return $ids;
    }
}
