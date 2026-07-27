<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Build base query for filtered transactions of user.
     */
    public function buildFilteredQuery(User $user, array $filters): Relation|Builder
    {
        return $user->transactions()
            ->with(['category', 'account', 'tags'])
            ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->whereDate('date', '>=', $v))
            ->when($filters['date_to'] ?? null, fn ($q, $v) => $q->whereDate('date', '<=', $v))
            ->when($filters['account_id'] ?? null, fn ($q, $v) => $q->where('account_id', $v))
            ->when($filters['category_id'] ?? null, fn ($q, $v) => $q->where('category_id', $v))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['currency_code'] ?? null, fn ($q, $v) => $q->where('currency_code', $v));
    }

    /**
     * Get aggregated totals & category breakdown for index response.
     */
    public function getAggregatedData(User $user, array $filters): array
    {
        $query = $this->buildFilteredQuery($user, $filters);

        // Summary totals for income & expense
        $totals = (clone $query)
            ->whereIn('type', ['income', 'expense'])
            ->select('type', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('type')
            ->get();

        $income  = 0.0;
        $expense = 0.0;

        foreach ($totals as $row) {
            $typeVal = $row->getAttribute('type');
            $type = $typeVal instanceof TransactionType ? $typeVal->value : (string) $typeVal;
            if ($type === 'income') {
                $income = (float) $row->getAttribute('total');
            } elseif ($type === 'expense') {
                $expense = (float) $row->getAttribute('total');
            }
        }

        // Category breakdown for expenses
        $byCategory = (clone $query)
            ->where('type', 'expense')
            ->whereNotNull('category_id')
            ->select('category_id', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('category_id')
            ->with('category')
            ->orderByDesc('total')
            ->get()
            ->map(function ($t) {
                $categoryName = null;
                if ($t instanceof Transaction) {
                    $categoryName = $t->category?->name;
                }
                return [
                    'category' => $categoryName,
                    'total'    => (float) $t->getAttribute('total'),
                    'count'    => (int) $t->getAttribute('count'),
                ];
            });

        return [
            'summary' => [
                'income'  => $income,
                'expense' => $expense,
                'net'     => round($income - $expense, 2),
            ],
            'by_category' => $byCategory,
            'filters'     => array_filter($filters, fn ($v) => $v !== null),
        ];
    }

    /**
     * Get flat collection of rows for export file generation.
     */
    public function getExportRows(User $user, array $filters): Collection
    {
        return $this->buildFilteredQuery($user, $filters)
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function (Transaction $transaction) {
                $typeVal = $transaction->type instanceof TransactionType ? $transaction->type->value : (string) $transaction->type;
                $tags = $transaction->tags->pluck('name')->implode(', ');

                return [
                    'date'     => $transaction->date ? $transaction->date->format('Y-m-d') : '',
                    'type'     => $typeVal,
                    'amount'   => (float) $transaction->amount,
                    'currency' => $transaction->currency_code,
                    'category' => $transaction->category?->name ?? '',
                    'account'  => $transaction->account?->name ?? '',
                    'comment'  => $transaction->comment ?? '',
                    'tags'     => $tags,
                ];
            });
    }

    /**
     * Count matching transactions for sync/async limit decision.
     */
    public function countFiltered(User $user, array $filters): int
    {
        return $this->buildFilteredQuery($user, $filters)->count();
    }

    /**
     * Resolve period preset string into [date_from, date_to].
     */
    public static function resolvePeriodDates(string $period): array
    {
        $now = Carbon::now();

        return match ($period) {
            'last_month'     => [$now->copy()->startOfMonth()->toDateString(), $now->copy()->toDateString()],
            'previous_month' => [$now->copy()->subMonth()->startOfMonth()->toDateString(), $now->copy()->subMonth()->endOfMonth()->toDateString()],
            '3_months'       => [$now->copy()->subMonths(3)->toDateString(), $now->copy()->toDateString()],
            '6_months'       => [$now->copy()->subMonths(6)->toDateString(), $now->copy()->toDateString()],
            '1_year'         => [$now->copy()->subYear()->toDateString(), $now->copy()->toDateString()],
            default          => [null, null],
        };
    }
}
