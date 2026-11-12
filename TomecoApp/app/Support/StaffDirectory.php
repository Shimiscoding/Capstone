<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class StaffDirectory
{
    public readonly string $search;

    public readonly string $sort;

    public function __construct(Request $request, string $defaultSort = 'latest')
    {
        $this->search = trim((string) $request->query('search', ''));
        $sort = $request->query('sort');
        $this->sort = in_array($sort, ['latest', 'oldest', 'name_asc', 'name_desc'], true)
            ? $sort
            : $defaultSort;
    }

    public function filter(Builder $query, string ...$additionalColumns): void
    {
        $columns = ['firstName', 'middleName', 'lastName', 'nameExtension', 'username', ...$additionalColumns];
        $terms = preg_split('/\s+/', $this->search, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($terms as $term) {
            $query->whereAny($columns, 'like', "%{$term}%");
        }
    }

    public function order(Builder $query): Builder
    {
        return match ($this->sort) {
            'name_asc' => $query->orderBy('firstName')->orderBy('lastName'),
            'name_desc' => $query->orderByDesc('firstName')->orderByDesc('lastName'),
            'oldest' => $query->oldest(),
            default => $query->latest(),
        };
    }
}
