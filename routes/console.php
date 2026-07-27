<?php

use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Services\TransactionService;
use Carbon\Carbon;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Process recurring transactions daily.
 *
 * For each active recurring transaction where next_run_at <= today:
 *   1. Create a real transaction via TransactionService.
 *   2. Advance next_run_at by the configured frequency interval.
 *
 * Both steps are wrapped in a single DB::transaction to prevent
 * duplicate transactions if the process crashes between create and update.
 *
 * Each record is processed in its own try/catch so that one failure
 * does not block processing for all other users.
 */
Schedule::call(function () {
    $today    = Carbon::today();
    $service  = app(TransactionService::class);

    RecurringTransaction::query()
        ->where('is_active', true)
        ->where('next_run_at', '<=', $today)
        ->with(['account', 'category', 'user'])
        ->each(function (RecurringTransaction $recurring) use ($today, $service) {
            try {
                DB::transaction(function () use ($recurring, $today, $service) {
                    // Create the real transaction scoped to the recurring record owner
                    $service->create($recurring->user, [
                        'account_id'    => $recurring->account_id,
                        'category_id'   => $recurring->category_id,
                        'type'          => $recurring->type->value,
                        'amount'        => $recurring->amount,
                        'currency_code' => $recurring->currency_code,
                        'date'          => $today->toDateString(),
                        'comment'       => $recurring->comment ?? $recurring->name,
                    ]);

                    // Advance next_run_at by one interval (inside the same DB transaction
                    // so both succeed or both roll back — prevents duplicate transactions).
                    $nextRun = match ($recurring->frequency->value) {
                        'daily'   => $recurring->next_run_at->copy()->addDay(),
                        'weekly'  => $recurring->next_run_at->copy()->addWeek(),
                        'monthly' => $recurring->next_run_at->copy()->addMonth(),
                        'yearly'  => $recurring->next_run_at->copy()->addYear(),
                    };

                    $recurring->update(['next_run_at' => $nextRun->toDateString()]);
                });
            } catch (\Throwable $e) {
                // Log the error and continue processing other recurring transactions.
                // Without this try/catch, one broken record would block the entire scheduler.
                Log::error('Failed to process recurring transaction', [
                    'recurring_transaction_id' => $recurring->id,
                    'user_id'                  => $recurring->user_id,
                    'error'                    => $e->getMessage(),
                ]);
            }
        });
})->daily()->name('process-recurring-transactions')->withoutOverlapping();

Schedule::command('export:cleanup')->hourly();

