<?php

namespace App\Services;

use App\Jobs\DeleteAccountJob;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AccountService
{
    public function getAll(User $user): Collection
    {
        $query = Account::where('user_id', $user->id);
        if (request()->boolean('archived')) {
            $query->withTrashed();
        }

        return $query->get();
    }

    public function create(User $user, array $data): Account
    {
        $account = $user->accounts()->create($data);
        if (! $account instanceof Account) {
            throw new \UnexpectedValueException('Expected Account model');
        }

        DashboardService::clearCache($user);

        return $account;
    }

    public function update(Account $account, array $data): Account
    {
        $account->update($data);
        DashboardService::clearCache($account->user_id);

        return $account;
    }

    public function delete(Account $account): void
    {
        // If account has > async limit transactions, offload deletion to a background queue job.
        if ($account->transactions()->count() > config('thresholds.async_processing_limit')) {
            DeleteAccountJob::dispatch($account);
            DashboardService::clearCache($account->user_id);

            return;
        }

        $this->performDeletion($account);
    }

    /**
     * Cascade soft-deletion of an account and its transactions.
     */
    public function performDeletion(Account $account): void
    {
        $userId = $account->user_id;

        DB::transaction(function () use ($account) {
            // cascadeOnDelete() only fires on hard delete, so we handle soft-delete cascade manually.
            // By deleting through TransactionService, we ensure that transfer pairs on other accounts
            // are also deleted and their balances reverted properly.
            $transactionService = app(TransactionService::class);
            $account->transactions()->get()->each(function (Model $transaction) use ($transactionService) {
                if ($transaction instanceof Transaction) {
                    $transactionService->delete($transaction);
                }
            });

            // Deactivate recurring transactions that reference this account.
            // Without this feature, the daily scheduler would try to create transactions
            // on a soft-deleted account, causing a fatal error that blocks all users.
            $account->recurringTransactions()->update(['is_active' => false]);

            $account->delete();
        });

        DashboardService::clearCache($userId);
    }
}
