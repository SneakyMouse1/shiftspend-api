<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\ReportPdfExport;
use App\Exports\TransactionsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExportReportRequest;
use App\Jobs\ExportReportJob;
use App\Models\ActivityLog;
use App\Models\ReportExport;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use OpenApi\Attributes as OA;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reportService,
        protected ReportPdfExport $pdfExport
    ) {}

    #[OA\Get(
        path: '/api/v1/reports',
        summary: 'Get aggregated transaction report with optional filters',
        description: 'Returns income/expense summary totals and a breakdown by category. Supports period presets and date ranges up to 366 days.',
        tags: ['Reports'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'period', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['last_month', 'previous_month', '3_months', '6_months', '1_year', 'custom'], example: 'last_month')),
            new OA\Parameter(name: 'date_from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date', example: '2026-01-01')),
            new OA\Parameter(name: 'date_to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date', example: '2026-12-31')),
            new OA\Parameter(name: 'account_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 1)),
            new OA\Parameter(name: 'category_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 2)),
            new OA\Parameter(name: 'type', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['income', 'expense', 'transfer'], example: 'expense')),
            new OA\Parameter(name: 'currency_code', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: 'USD')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Report data with summary and category breakdown',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'summary', properties: [
                            new OA\Property(property: 'income', type: 'number', example: 3000.00),
                            new OA\Property(property: 'expense', type: 'number', example: 1200.00),
                            new OA\Property(property: 'net', type: 'number', example: 1800.00),
                        ], type: 'object'),
                        new OA\Property(property: 'by_category', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'filters', type: 'object'),
                    ], type: 'object'),
                ])
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $filters = $request->only(['period', 'date_from', 'date_to', 'account_id', 'category_id', 'type', 'currency_code']);

        if (! empty($filters['period']) && $filters['period'] !== 'custom') {
            [$from, $to] = ReportService::resolvePeriodDates($filters['period']);
            $filters['date_from'] = $from;
            $filters['date_to'] = $to;
        }

        $data = $this->reportService->getAggregatedData($user, $filters);

        return response()->json(['data' => $data]);
    }

    #[OA\Get(
        path: '/api/v1/reports/export',
        summary: 'Export financial report (CSV / Excel / PDF)',
        description: 'Generates export synchronously for <= 500 transactions or dispatches a background job returning 202 Accepted for larger datasets.',
        tags: ['Reports'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'format', in: 'query', required: true, schema: new OA\Schema(type: 'string', enum: ['csv', 'excel', 'pdf'], example: 'csv')),
            new OA\Parameter(name: 'period', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['last_month', 'previous_month', '3_months', '6_months', '1_year', 'custom'], example: 'last_month')),
            new OA\Parameter(name: 'date_from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date', example: '2026-01-01')),
            new OA\Parameter(name: 'date_to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date', example: '2026-12-31')),
            new OA\Parameter(name: 'account_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 1)),
            new OA\Parameter(name: 'category_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 2)),
            new OA\Parameter(name: 'type', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['income', 'expense', 'transfer'])),
            new OA\Parameter(name: 'currency_code', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: 'USD')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Direct file download (Synchronous mode)'),
            new OA\Response(
                response: 202,
                description: 'Export processing in queue (Asynchronous mode)',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'pending'),
                        new OA\Property(property: 'export_key', type: 'string', example: '550e8400-e29b-41d4-a716-446655440000'),
                        new OA\Property(property: 'check_url', type: 'string'),
                        new OA\Property(property: 'message', type: 'string'),
                    ], type: 'object'),
                ])
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 429, description: 'Rate limit exceeded (5 req/min)'),
        ]
    )]
    public function export(ExportReportRequest $request): mixed
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        @ini_set('memory_limit', '512M');

        $format = (string) $request->validated('format');
        $filters = $request->validated();

        $count = $this->reportService->countFiltered($user, $filters);

        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'report_exported',
            'model' => 'Transaction',
            'model_id' => null,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'format' => $format,
                'count' => $count,
                'filters' => $filters,
            ],
        ]);

        $syncLimit = (int) config('reports.export_sync_limit', 500);
        $exportKey = Str::uuid()->toString();
        $ttlHours = (int) config('reports.export_ttl_hours', 24);
        $disk = config('reports.storage_disk', 'local');

        $ext = match ($format) {
            'excel' => 'xlsx',
            'pdf'   => 'pdf',
            default => 'csv',
        };
        $filePath = "exports/{$user->id}/{$exportKey}.{$ext}";

        // --- SYNCHRONOUS MODE ---
        if ($count <= $syncLimit) {
            $rows = $this->reportService->getExportRows($user, $filters);

            if ($format === 'pdf') {
                $aggregated = $this->reportService->getAggregatedData($user, $filters);
                $pdfContent = $this->pdfExport->generate([
                    'user'       => $user,
                    'rows'       => $rows,
                    'summary'    => $aggregated['summary'],
                    'byCategory' => $aggregated['by_category'],
                    'filters'    => $filters,
                ]);
                Storage::disk($disk)->put($filePath, $pdfContent);
            } elseif ($format === 'excel') {
                $raw = Excel::raw(new TransactionsExport($rows), \Maatwebsite\Excel\Excel::XLSX);
                Storage::disk($disk)->put($filePath, $raw);
            } else {
                $raw = Excel::raw(new TransactionsExport($rows), \Maatwebsite\Excel\Excel::CSV);
                Storage::disk($disk)->put($filePath, $raw);
            }

            $periodLabel = $this->formatPeriodLabel($filters);

            ReportExport::create([
                'key'        => $exportKey,
                'user_id'    => $user->id,
                'status'     => 'done',
                'format'     => $format,
                'period'     => $periodLabel,
                'file_path'  => $filePath,
                'expires_at' => now()->addHours($ttlHours),
            ]);

            $downloadFilename = "transactions_report_{$exportKey}.{$ext}";
            return Storage::disk($disk)->download($filePath, $downloadFilename, [
                'Access-Control-Expose-Headers' => 'X-Export-Key',
                'X-Export-Key'                  => $exportKey,
            ]);
        }

        // --- ASYNCHRONOUS MODE ---
        $reportExport = ReportExport::create([
            'key'        => $exportKey,
            'user_id'    => $user->id,
            'status'     => 'pending',
            'format'     => $format,
            'period'     => $this->formatPeriodLabel($filters),
            'expires_at' => now()->addHours($ttlHours),
        ]);

        ExportReportJob::dispatch($reportExport->id, $filters);

        return response()->json([
            'data' => [
                'status'     => 'pending',
                'export_key' => $exportKey,
                'check_url'  => route('api.v1.reports.export.status', $exportKey),
                'message'    => 'Export is being generated. Poll the check_url to get download link.',
            ],
        ], 202);
    }

    public function exportsList(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $disk = config('reports.storage_disk', 'local');

        $exports = ReportExport::where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (ReportExport $export) use ($disk) {
                $sizeBytes = null;
                $sizeFormatted = null;

                if ($export->file_path && Storage::disk($disk)->exists($export->file_path)) {
                    $sizeBytes = Storage::disk($disk)->size($export->file_path);
                    $sizeFormatted = $this->formatBytes($sizeBytes);
                }

                return [
                    'id'                  => $export->id,
                    'key'                 => $export->key,
                    'format'              => $export->format,
                    'period'              => $export->period ?? 'Report',
                    'status'              => $export->status,
                    'file_size'           => $sizeBytes,
                    'file_size_formatted' => $sizeFormatted,
                    'error'               => $export->error,
                    'created_at'          => $export->created_at->toIso8601String(),
                    'expires_at'          => $export->expires_at->toIso8601String(),
                    'download_url'        => $export->status === 'done' ? route('api.v1.reports.export.download', $export->key) : null,
                ];
            });

        return response()->json(['data' => $exports]);
    }

    public function deleteExport(string $key, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $export = ReportExport::where('key', $key)->first();
        if (! $export) {
            return response()->json(['error' => 'Export record not found.'], 404);
        }

        if ($export->user_id !== $user->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $disk = config('reports.storage_disk', 'local');

        // Atomic deletion of file and DB record
        if ($export->file_path && Storage::disk($disk)->exists($export->file_path)) {
            Storage::disk($disk)->delete($export->file_path);
        }

        $export->delete();

        return response()->json(['message' => 'Export deleted successfully.']);
    }

    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    #[OA\Get(
        path: '/api/v1/reports/export/status/{key}',
        summary: 'Check status of async report export',
        tags: ['Reports'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'key', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Status payload (pending / processing / done / failed / expired)'),
            new OA\Response(response: 403, description: 'Forbidden — export key belongs to another user'),
            new OA\Response(response: 404, description: 'Export key not found'),
        ]
    )]
    public function exportStatus(string $key, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $export = ReportExport::where('key', $key)->first();
        if (! $export) {
            return response()->json(['error' => 'Export request not found.'], 404);
        }

        if ($export->user_id !== $user->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        if ($export->expires_at->isPast()) {
            return response()->json([
                'data' => [
                    'status' => 'expired',
                    'message' => 'Export link has expired.',
                ],
            ]);
        }

        if ($export->status === 'done') {
            return response()->json([
                'data' => [
                    'status' => 'done',
                    'download_url' => route('api.v1.reports.export.download', $export->key),
                    'expires_at' => $export->expires_at->toIso8601String(),
                ],
            ]);
        }

        return response()->json([
            'data' => [
                'status' => $export->status,
                'error' => $export->error,
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/v1/reports/export/download/{key}',
        summary: 'Download generated report export file',
        tags: ['Reports'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'key', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Binary file download stream'),
            new OA\Response(response: 403, description: 'Forbidden — export key belongs to another user'),
            new OA\Response(response: 404, description: 'Export file not found or expired'),
        ]
    )]
    public function exportDownload(string $key, Request $request): mixed
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $export = ReportExport::where('key', $key)->first();
        if (! $export) {
            return response()->json(['error' => 'Export file not found.'], 404);
        }

        if ($export->user_id !== $user->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        if ($export->status !== 'done' || $export->expires_at->isPast() || ! $export->file_path) {
            return response()->json(['error' => 'Export file is not available or has expired.'], 410);
        }

        $disk = config('reports.storage_disk', 'local');
        if (! Storage::disk($disk)->exists($export->file_path)) {
            return response()->json(['error' => 'Export file missing on storage disk.'], 404);
        }

        $ext = match ($export->format) {
            'excel' => 'xlsx',
            'pdf' => 'pdf',
            default => 'csv',
        };

        $filename = "shiftspend_report_{$key}.{$ext}";

        return Storage::disk($disk)->download($export->file_path, $filename);
    }

    private function formatPeriodLabel(array $filters): string
    {
        $period = $filters['period'] ?? 'last_month';
        return match ($period) {
            'last_month'          => 'This Month',
            'previous_month'      => 'Last Month',
            '3_months'            => '3 Months',
            '6_months'            => '6 Months',
            'this_year', '1_year' => 'This Year',
            'custom'              => isset($filters['date_from'], $filters['date_to'])
                ? "{$filters['date_from']} – {$filters['date_to']}"
                : 'Custom Period',
            default               => ucfirst(str_replace('_', ' ', $period)),
        };
    }
}
