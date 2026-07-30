<?php

namespace App\Http\Requests;

use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ExportReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $period = $this->input('period');
        if ($period && $period !== 'custom') {
            [$from, $to] = ReportService::resolvePeriodDates($period);
            $this->merge([
                'date_from' => $from,
                'date_to' => $to,
            ]);
        }
    }

    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'format' => ['required', 'string', 'in:csv,excel,pdf'],
            'period' => ['nullable', 'string', 'in:last_month,previous_month,3_months,6_months,1_year,this_year,custom'],
            'date_from' => ['nullable', 'date_format:Y-m-d', 'required_if:period,custom'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from', 'required_if:period,custom'],
            'account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->where('user_id', $userId)],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('user_id', $userId)],
            'type' => ['nullable', 'string', 'in:income,expense,transfer'],
            'currency_code' => ['nullable', 'string', 'max:10'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $from = $this->input('date_from');
            $to = $this->input('date_to');

            if ($from && $to && Carbon::hasFormat($from, 'Y-m-d') && Carbon::hasFormat($to, 'Y-m-d')) {
                $fromDate = Carbon::parse($from);
                $toDate = Carbon::parse($to);
                if ($fromDate->lte($toDate)) {
                    $diff = $fromDate->diffInDays($toDate);
                    $maxDays = (int) config('reports.max_period_days', 366);
                    if ($diff > $maxDays) {
                        $v->errors()->add('date_to', "Report period cannot exceed 1 year ({$maxDays} days).");
                    }
                }
            }
        });
    }
}
