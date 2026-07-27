<?php

namespace App\Console\Commands;

use App\Models\ReportExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ExportCleanupCommand extends Command
{
    protected $signature = 'export:cleanup';

    protected $description = 'Delete expired report exports and their generated files from storage';

    public function handle(): int
    {
        $expiredExports = ReportExport::where('expires_at', '<', now())->get();
        $disk = config('reports.storage_disk', 'local');
        $count = 0;

        foreach ($expiredExports as $export) {
            if ($export->file_path && Storage::disk($disk)->exists($export->file_path)) {
                Storage::disk($disk)->delete($export->file_path);
            }

            $export->delete();
            $count++;
        }

        $this->info("Cleaned up {$count} expired report exports.");

        return Command::SUCCESS;
    }
}
