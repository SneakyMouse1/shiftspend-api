<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Financial Report — Shift Spend</title>
    <style>
        @page {
            margin: 25px 30px;
        }

        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 11px;
            color: #1e293b;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }

        .header {
            border-bottom: 2px solid #0f172a;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }

        .header table {
            width: 100%;
        }

        .brand {
            font-size: 20px;
            font-weight: bold;
            color: #0f172a;
            letter-spacing: -0.5px;
        }

        .subtitle {
            font-size: 12px;
            color: #64748b;
            margin-top: 2px;
        }

        .meta-text {
            text-align: right;
            font-size: 10px;
            color: #64748b;
        }

        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
            margin-top: 18px;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .summary-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .summary-grid td {
            width: 33.33%;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            background-color: #f8fafc;
            vertical-align: top;
        }

        .summary-label {
            font-size: 9px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: bold;
        }

        .summary-value {
            font-size: 15px;
            font-weight: bold;
            margin-top: 4px;
        }

        .text-income {
            color: #166534;
        }

        .text-expense {
            color: #991b1b;
        }

        .text-net {
            color: #1e40af;
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            margin-bottom: 15px;
        }

        table.data-table th {
            background-color: #0f172a;
            color: #ffffff;
            font-weight: bold;
            font-size: 10px;
            text-align: left;
            padding: 7px 8px;
            text-transform: uppercase;
        }

        table.data-table td {
            padding: 6px 8px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 10px;
            vertical-align: middle;
        }

        table.data-table tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .text-left {
            text-align: left !important;
        }

        .text-right {
            text-align: right !important;
        }

        .text-center {
            text-align: center !important;
        }

        .badge-income {
            color: #166534;
            font-weight: bold;
        }

        .badge-expense {
            color: #991b1b;
            font-weight: bold;
        }

        .badge-transfer {
            color: #1e40af;
            font-weight: bold;
        }

        .filters-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 6px 10px;
            border-radius: 6px;
            margin-bottom: 15px;
        }

        .filters-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
            padding: 0;
        }

        .filters-title-cell {
            width: 1%;
            white-space: nowrap;
            padding-right: 8px;
            vertical-align: middle;
            border: none !important;
            background: transparent !important;
        }

        .filters-title-text {
            display: inline-block;
            vertical-align: middle;
            font-weight: bold;
            color: #475569;
            text-transform: uppercase;
            font-size: 9px;
            letter-spacing: 0.5px;
            padding: 4px 0;
            line-height: 1.2;
        }

        .filters-chips-cell {
            vertical-align: middle;
            border: none !important;
            background: transparent !important;
        }

        .filter-chip {
            display: inline-block;
            vertical-align: middle;
            background-color: #ffffff;
            border: 1px solid #cbd5e1;
            padding: 3px 8px;
            border-radius: 4px;
            margin-right: 6px;
            font-size: 9.5px;
            color: #0f172a;
            line-height: 1.2;
        }

        .filter-chip strong {
            color: #64748b;
            font-weight: 600;
        }

        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            font-size: 9px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 6px;
            text-align: center;
        }
    </style>
</head>

<body>

    <div class="header">
        <table>
            <tr>
                <td>
                    <div class="brand">Shift Spend</div>
                    <div class="subtitle">Financial Report</div>
                </td>
                <td class="meta-text">
                    <div><strong>User:</strong> {{ $user->name ?? $user->email }}</div>
                    <div><strong>Generated:</strong> {{ now()->format('Y-m-d H:i') }}</div>
                </td>
            </tr>
        </table>
    </div>

    @php
        $formattedFilters = [];

        if (!empty($filters['period'])) {
            $p = $filters['period'];
            $periodLabel = match ($p) {
                'this_month' => 'This Month',
                'last_month', 'previous_month' => 'Last Month',
                '3_months' => 'Last 3 Months',
                '6_months' => 'Last 6 Months',
                'this_year', '1_year' => 'This Year',
                'custom' => 'Custom Period',
                default => ucfirst(str_replace('_', ' ', $p)),
            };
            $formattedFilters['Period'] = $periodLabel;
        }

        if (!empty($filters['date_from'])) {
            $formattedFilters['Date From'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $formattedFilters['Date To'] = $filters['date_to'];
        }

        if (!empty($filters['type'])) {
            $formattedFilters['Type'] = ucfirst($filters['type']);
        }

        if (!empty($filters['currency_code'])) {
            $formattedFilters['Currency'] = strtoupper($filters['currency_code']);
        }

        $ignoreKeys = [
            'format',
            'export',
            'page',
            'per_page',
            'sort',
            'order',
            'period',
            'date_from',
            'date_to',
            'type',
            'currency_code',
        ];
        foreach ($filters as $k => $v) {
            if (!in_array($k, $ignoreKeys) && !empty($v)) {
                $label = ucfirst(str_replace('_', ' ', $k));
                $formattedFilters[$label] = is_array($v) ? implode(', ', $v) : $v;
            }
        }
    @endphp

    @if (!empty($formattedFilters))
        <div class="filters-box">
            <table class="filters-table">
                <tr>
                    <td class="filters-title-cell">
                        <span class="filters-title-text">Applied Filters:</span>
                    </td>
                    <td class="filters-chips-cell">
                        @foreach ($formattedFilters as $label => $val)
                            <span class="filter-chip">
                                <strong>{{ $label }}:</strong> {{ $val }}
                            </span>
                        @endforeach
                    </td>
                </tr>
            </table>
        </div>
    @endif

    <div class="section-title">Summary Overview</div>
    <table class="summary-grid">
        <tr>
            <td>
                <div class="summary-label">Total Income</div>
                <div class="summary-value text-income">{{ number_format($summary['income'] ?? 0, 2) }}</div>
            </td>
            <td>
                <div class="summary-label">Total Expenses</div>
                <div class="summary-value text-expense">{{ number_format($summary['expense'] ?? 0, 2) }}</div>
            </td>
            <td>
                <div class="summary-label">Net Balance</div>
                <div class="summary-value text-net">{{ number_format($summary['net'] ?? 0, 2) }}</div>
            </td>
        </tr>
    </table>

    @if (!empty($byCategory) && count($byCategory) > 0)
        <div class="section-title">Expense Breakdown by Category</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 50%;">Category</th>
                    <th style="width: 20%;" class="text-center">Count</th>
                    <th style="width: 30%;" class="text-right">Total Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($byCategory as $cat)
                    <tr>
                        <td>{{ $cat['category'] ?? 'Uncategorized' }}</td>
                        <td class="text-center">{{ $cat['count'] }}</td>
                        <td class="text-right text-expense">{{ number_format($cat['total'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="section-title">Detailed Transactions</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 13%;">Date</th>
                <th style="width: 11%;">Type</th>
                <th style="width: 20%;">Category</th>
                <th style="width: 18%;">Account</th>
                <th style="width: 22%;">Comment</th>
                <th style="width: 16%;" class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row['date'] }}</td>
                    <td class="badge-{{ $row['type'] }}">{{ strtoupper($row['type']) }}</td>
                    <td>{{ $row['category'] ?: '—' }}</td>
                    <td>{{ $row['account'] }}</td>
                    <td>{{ $row['comment'] ?: '—' }}</td>
                    <td
                        class="text-right {{ $row['type'] === 'income' ? 'text-income' : ($row['type'] === 'expense' ? 'text-expense' : 'text-net') }}">
                        {{ number_format($row['amount'], 2) }} {{ $row['currency'] }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center" style="padding: 15px; color: #94a3b8;">
                        No transactions found for the selected period/filters.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Generated automatically by Shift Spend API &bull; Confidential Personal Financial Report
    </div>

</body>

</html>
