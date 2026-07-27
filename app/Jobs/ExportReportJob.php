<?php

namespace App\Jobs;

use App\Exports\ReportPdfExport;
use App\Exports\TransactionsExport;
use App\Models\ReportExport;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ExportReportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public readonly int $reportExportId,
        public readonly array $filters = []
    ) {}

    public function handle(ReportService $reportService, ReportPdfExport $pdfExport): void
    {
        $export = ReportExport::find($this->reportExportId);
        if (! $export) {
            return;
        }

        // Avoid re-processing if already done
        if ($export->status === 'done' && $export->file_path && Storage::disk(config('reports.storage_disk', 'local'))->exists($export->file_path)) {
            return;
        }

        $export->update(['status' => 'processing']);

        try {
            $user = User::findOrFail($export->user_id);
            $rows = $reportService->getExportRows($user, $this->filters);

            $ext = match ($export->format) {
                'excel' => 'xlsx',
                'pdf'   => 'pdf',
                default => 'csv',
            };

            $filePath = "exports/{$user->id}/{$export->key}.{$ext}";
            $disk = config('reports.storage_disk', 'local');

            if ($export->format === 'pdf') {
                $aggregated = $reportService->getAggregatedData($user, $this->filters);
                $pdfContent = $pdfExport->generate([
                    'user'       => $user,
                    'rows'       => $rows,
                    'summary'    => $aggregated['summary'],
                    'byCategory' => $aggregated['by_category'],
                    'filters'    => $this->filters,
                ]);
                Storage::disk($disk)->put($filePath, $pdfContent);
            } elseif ($export->format === 'excel') {
                $raw = Excel::raw(new TransactionsExport($rows), \Maatwebsite\Excel\Excel::XLSX);
                Storage::disk($disk)->put($filePath, $raw);
            } else {
                $raw = Excel::raw(new TransactionsExport($rows), \Maatwebsite\Excel\Excel::CSV);
                Storage::disk($disk)->put($filePath, $raw);
            }

            $export->update([
                'status'    => 'done',
                'file_path' => $filePath,
                'error'     => null,
            ]);
        } catch (\Throwable $e) {
            $export->update([
                'status' => 'failed',
                'error'  => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        $export = ReportExport::find($this->reportExportId);
        if ($export) {
            $export->update([
                'status' => 'failed',
                'error'  => $e->getMessage(),
            ]);
        }
    }
}
