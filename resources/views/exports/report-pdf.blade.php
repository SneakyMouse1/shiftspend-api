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
            margin-bottom: 20px;
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
            font-size: 13px;
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
            padding: 6px 8px;
            text-transform: uppercase;
        }

        table.data-table td {
            padding: 6px 8px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 10px;
        }

        table.data-table tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
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
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            border-radius: 4px;
            font-size: 9px;
            color: #475569;
            margin-bottom: 15px;
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

    @if (!empty($filters))
        <div class="filters-box">
            <strong>Applied Filters:</strong>
            <br>
            @foreach ($filters as $k => $v)
                <span style="margin-right: 12px;"><strong>{{ $k }}:</strong>
                    {{ is_array($v) ? implode(', ', $v) : $v }}</span>
            @endforeach
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
                    <th>Category</th>
                    <th class="text-center">Count</th>
                    <th class="text-right">Total Amount</th>
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
                <th style="width: 12%;">Date</th>
                <th style="width: 10%;">Type</th>
                <th style="width: 18%;">Category</th>
                <th style="width: 18%;">Account</th>
                <th style="width: 27%;">Comment</th>
                <th style="width: 15%;" class="text-right">Amount</th>
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
