<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TransactionsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(
        protected Collection $rows
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'Date',
            'Type',
            'Amount',
            'Currency',
            'Category',
            'Account',
            'Comment',
            'Tags',
        ];
    }

    public function map($row): array
    {
        $data = is_array($row) ? $row : (array) $row;

        return [
            $this->sanitize($data['date'] ?? ''),
            $this->sanitize($data['type'] ?? ''),
            $data['amount'] ?? 0.0,
            $this->sanitize($data['currency'] ?? ''),
            $this->sanitize($data['category'] ?? ''),
            $this->sanitize($data['account'] ?? ''),
            $this->sanitize($data['comment'] ?? ''),
            $this->sanitize($data['tags'] ?? ''),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    /**
     * Escape strings that start with formula trigger characters to prevent CSV Injection.
     */
    protected function sanitize(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        $firstChar = substr($value, 0, 1);
        if (in_array($firstChar, ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }

        return $value;
    }
}
