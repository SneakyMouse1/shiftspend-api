<?php

namespace Tests\Feature\Api\V1;

use App\Exports\TransactionsExport;
use App\Jobs\ExportReportJob;
use App\Models\Account;
use App\Models\Category;
use App\Models\ReportExport;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_generate_filtered_report()
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $category1 = Category::factory()->create(['user_id' => $user->id, 'name' => 'Food']);
        $category2 = Category::factory()->create(['user_id' => $user->id, 'name' => 'Salary']);

        Transaction::factory()->create([
            'user_id'     => $user->id,
            'account_id'  => $account->id,
            'category_id' => $category1->id,
            'type'        => 'expense',
            'amount'      => 75.50,
            'date'        => '2026-07-03',
        ]);

        Transaction::factory()->create([
            'user_id'     => $user->id,
            'account_id'  => $account->id,
            'category_id' => $category2->id,
            'type'        => 'income',
            'amount'      => 1000.00,
            'date'        => '2026-07-03',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/reports?date_from=2026-07-01&date_to=2026-07-31');

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'summary' => [
                    'income'  => 1000.00,
                    'expense' => 75.50,
                    'net'     => 924.50,
                ],
                'by_category' => [
                    [
                        'category' => 'Food',
                        'total'    => 75.50,
                        'count'    => 1,
                    ],
                ],
                'filters' => [
                    'date_from' => '2026-07-01',
                    'date_to'   => '2026-07-31',
                ],
            ],
        ]);
    }

    public function test_report_index_accepts_period_preset()
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        Transaction::factory()->create([
            'user_id'    => $user->id,
            'account_id' => $account->id,
            'type'       => 'income',
            'amount'     => 500.00,
            'date'       => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/reports?period=last_month');

        $response->assertStatus(200);
        $this->assertEquals(500.00, $response->json('data.summary.income'));
    }

    public function test_export_csv_sync_returns_download()
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        Transaction::factory()->create([
            'user_id'    => $user->id,
            'account_id' => $account->id,
            'type'       => 'income',
            'amount'     => 150.00,
            'date'       => '2026-07-10',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/reports/export?format=csv&period=last_month');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }

    public function test_export_excel_sync_returns_download()
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        Transaction::factory()->create([
            'user_id'    => $user->id,
            'account_id' => $account->id,
            'type'       => 'expense',
            'amount'     => 45.00,
            'date'       => '2026-07-10',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/reports/export?format=excel&period=last_month');

        $response->assertStatus(200);
        $this->assertStringContainsString('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
    }

    public function test_export_pdf_sync_returns_download()
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        Transaction::factory()->create([
            'user_id'    => $user->id,
            'account_id' => $account->id,
            'type'       => 'expense',
            'amount'     => 20.00,
            'date'       => '2026-07-10',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/reports/export?format=pdf&period=last_month');

        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_export_async_dispatches_job_and_returns_202()
    {
        Queue::fake();

        config(['reports.export_sync_limit' => 2]);

        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        Transaction::factory()->count(3)->create([
            'user_id'    => $user->id,
            'account_id' => $account->id,
            'type'       => 'expense',
            'amount'     => 10.00,
            'date'       => '2026-07-10',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/reports/export?format=csv&period=last_month');

        $response->assertStatus(202);
        $response->assertJsonPath('data.status', 'pending');
        $this->assertNotNull($response->json('data.export_key'));

        Queue::assertPushed(ExportReportJob::class);
        $this->assertDatabaseHas('report_exports', [
            'user_id' => $user->id,
            'status'  => 'pending',
            'format'  => 'csv',
        ]);
    }

    public function test_export_status_returns_pending()
    {
        $user = User::factory()->create();
        $export = ReportExport::create([
            'key'        => '550e8400-e29b-41d4-a716-446655440000',
            'user_id'    => $user->id,
            'status'     => 'pending',
            'format'     => 'csv',
            'expires_at' => now()->addHours(2),
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/reports/export/status/{$export->key}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'pending');
    }

    public function test_export_status_returns_done_with_download_url()
    {
        $user = User::factory()->create();
        $export = ReportExport::create([
            'key'        => '550e8400-e29b-41d4-a716-446655440001',
            'user_id'    => $user->id,
            'status'     => 'done',
            'format'     => 'csv',
            'file_path'  => "exports/{$user->id}/550e8400-e29b-41d4-a716-446655440001.csv",
            'expires_at' => now()->addHours(2),
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/reports/export/status/{$export->key}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'done');
        $this->assertStringContainsString('/api/v1/reports/export/download/', $response->json('data.download_url'));
    }

    public function test_export_download_requires_ownership()
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $export = ReportExport::create([
            'key'        => '550e8400-e29b-41d4-a716-446655440002',
            'user_id'    => $userA->id,
            'status'     => 'done',
            'format'     => 'csv',
            'file_path'  => "exports/{$userA->id}/550e8400-e29b-41d4-a716-446655440002.csv",
            'expires_at' => now()->addHours(2),
        ]);

        // User B attempts to access User A's export status & download
        $statusResponse = $this->actingAs($userB)
            ->getJson("/api/v1/reports/export/status/{$export->key}");
        $statusResponse->assertStatus(403);

        $downloadResponse = $this->actingAs($userB)
            ->getJson("/api/v1/reports/export/download/{$export->key}");
        $downloadResponse->assertStatus(403);
    }

    public function test_export_account_id_ownership_validation()
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $accountB = Account::factory()->create(['user_id' => $userB->id]);

        $response = $this->actingAs($userA)
            ->getJson("/api/v1/reports/export?format=csv&period=custom&date_from=2026-07-01&date_to=2026-07-15&account_id={$accountB->id}");

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['account_id']);
    }

    public function test_export_rejects_period_over_366_days()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/reports/export?format=csv&period=custom&date_from=2025-01-01&date_to=2026-06-01');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['date_to']);
    }

    public function test_export_accepts_period_of_exactly_366_days()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/reports/export?format=csv&period=custom&date_from=2025-01-01&date_to=2026-01-02');

        $response->assertStatus(200);
    }

    public function test_export_date_to_before_date_from_fails()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/reports/export?format=csv&period=custom&date_from=2026-07-20&date_to=2026-01-01');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['date_to']);
    }

    public function test_export_format_validation_fails_for_unknown_format()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/reports/export?format=xml&period=last_month');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['format']);
    }

    public function test_export_csv_sanitizes_injection_strings()
    {
        $exporter = new TransactionsExport(collect([
            [
                'date'     => '2026-07-26',
                'type'     => 'expense',
                'amount'   => 10.00,
                'currency' => 'USD',
                'category' => '=CMD|\'/C calc\'!A0',
                'account'  => '+SUM(1,2)',
                'comment'  => '-1+1',
                'tags'     => '@tag',
            ]
        ]));

        $mapped = $exporter->map($exporter->collection()->first());

        $this->assertEquals("'=CMD|'/C calc'!A0", $mapped[4]);
        $this->assertEquals("'+SUM(1,2)", $mapped[5]);
        $this->assertEquals("'-1+1", $mapped[6]);
        $this->assertEquals("'@tag", $mapped[7]);
    }

    public function test_export_cleanup_command_removes_expired_exports_and_files()
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $filePath = "exports/{$user->id}/test-expired-file.csv";
        Storage::disk('local')->put($filePath, 'dummy csv content');

        $export = ReportExport::create([
            'key'        => '550e8400-e29b-41d4-a716-446655440099',
            'user_id'    => $user->id,
            'status'     => 'done',
            'format'     => 'csv',
            'file_path'  => $filePath,
            'expires_at' => now()->subHour(),
        ]);

        $this->assertDatabaseHas('report_exports', ['id' => $export->id]);
        Storage::disk('local')->assertExists($filePath);

        Artisan::call('export:cleanup');

        $this->assertDatabaseMissing('report_exports', ['id' => $export->id]);
        Storage::disk('local')->assertMissing($filePath);
    }

    public function test_report_export_rate_limiting_triggers_at_31st_request()
    {
        $user = User::factory()->create();

        RateLimiter::clear('reports/export:127.0.0.1');

        for ($i = 0; $i < 30; $i++) {
            $response = $this->actingAs($user)
                ->getJson('/api/v1/reports/export?format=csv&period=last_month');

            $response->assertStatus(200);
        }

        $response = $this->actingAs($user)
            ->getJson('/api/v1/reports/export?format=csv&period=last_month');
        $response->assertStatus(429);
    }

    public function test_period_preset_last_month_resolves_correct_dates()
    {
        Carbon::setTestNow('2026-07-20');
        [$from, $to] = \App\Services\ReportService::resolvePeriodDates('last_month');
        $this->assertEquals('2026-07-01', $from);
        $this->assertEquals('2026-07-20', $to);
        Carbon::setTestNow();
    }

    public function test_period_preset_previous_month_resolves_correct_dates()
    {
        Carbon::setTestNow('2026-07-20');
        [$from, $to] = \App\Services\ReportService::resolvePeriodDates('previous_month');
        $this->assertEquals('2026-06-01', $from);
        $this->assertEquals('2026-06-30', $to);
        Carbon::setTestNow();
    }

    public function test_period_preset_3_months_resolves_correct_dates()
    {
        Carbon::setTestNow('2026-07-20');
        [$from, $to] = \App\Services\ReportService::resolvePeriodDates('3_months');
        $this->assertEquals('2026-04-20', $from);
        $this->assertEquals('2026-07-20', $to);
        Carbon::setTestNow();
    }

    public function test_period_preset_6_months_resolves_correct_dates()
    {
        Carbon::setTestNow('2026-07-20');
        [$from, $to] = \App\Services\ReportService::resolvePeriodDates('6_months');
        $this->assertEquals('2026-01-20', $from);
        $this->assertEquals('2026-07-20', $to);
        Carbon::setTestNow();
    }

    public function test_period_preset_1_year_resolves_correct_dates()
    {
        Carbon::setTestNow('2026-07-20');
        [$from, $to] = \App\Services\ReportService::resolvePeriodDates('1_year');
        $this->assertEquals('2026-01-01', $from);
        $this->assertEquals('2026-07-20', $to);
        Carbon::setTestNow();
    }

    public function test_period_custom_requires_date_from_and_date_to()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/reports/export?format=csv&period=custom');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['date_from', 'date_to']);
    }

    public function test_user_can_list_and_delete_exports_atomically()
    {
        $user = User::factory()->create();
        $disk = config('reports.storage_disk', 'local');
        $filePath = "exports/{$user->id}/test-export.csv";
        Storage::disk($disk)->put($filePath, "Date,Amount\n2026-07-01,100");

        $export = ReportExport::create([
            'key'        => 'test-key-123',
            'user_id'    => $user->id,
            'status'     => 'done',
            'format'     => 'csv',
            'file_path'  => $filePath,
            'expires_at' => now()->addHours(24),
        ]);

        $listResponse = $this->actingAs($user)->getJson('/api/v1/reports/exports');
        $listResponse->assertStatus(200);
        $listResponse->assertJsonFragment(['key' => 'test-key-123']);

        $deleteResponse = $this->actingAs($user)->deleteJson("/api/v1/reports/exports/{$export->key}");
        $deleteResponse->assertStatus(200);

        $this->assertDatabaseMissing('report_exports', ['id' => $export->id]);
        Storage::disk($disk)->assertMissing($filePath);
    }
}
