<?php

namespace App\Http\Requests\Tenant;

use App\Models\TimeOff;
use App\Tenancy\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use LogicException;

/**
 * Whole days of leave, entered as local dates in the workspace's timezone.
 */
class StoreTimeOffRequest extends FormRequest
{
    private const MAX_DAYS = 366;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // "Today" is the workspace's today: in Jayapura (WIT) it starts two hours before Jakarta's.
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$this->today()],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'start_date.after_or_equal' => 'Time off cannot start in the past.',
            'end_date.after_or_equal' => 'The last day cannot be before the first day.',
        ];
    }

    /**
     * Checks that need both dates: the length, and clashes with leave already booked.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                [$start, $end] = $this->period();

                if ($start->diffInDays($end) > self::MAX_DAYS) {
                    $validator->errors()->add('end_date', 'Time off can last at most a year.');

                    return;
                }

                // Two periods overlap when each starts before the other ends.
                $clashes = TimeOff::query()
                    ->where('user_id', $this->user()?->id)
                    ->where('starts_at', '<', $end)
                    ->where('ends_at', '>', $start)
                    ->exists();

                if ($clashes) {
                    $validator->errors()->add('start_date', 'You already have time off during these dates.');
                }
            },
        ];
    }

    /**
     * The leave as exact moments: from the first day's midnight up to the midnight after the last
     * day, in the workspace's timezone (Carbon keeps the moment; it is stored as UTC).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function period(): array
    {
        $timezone = $this->timezone();

        $start = CarbonImmutable::createFromFormat('!Y-m-d', $this->string('start_date')->value(), $timezone);
        $lastDay = CarbonImmutable::createFromFormat('!Y-m-d', $this->string('end_date')->value(), $timezone);

        if ($start === null || $lastDay === null) {
            throw new LogicException('period() is only called after the dates were validated.');
        }

        return [$start, $lastDay->addDay()];
    }

    private function today(): string
    {
        return CarbonImmutable::now($this->timezone())->toDateString();
    }

    private function timezone(): string
    {
        return CurrentTenant::resolve()?->tenant->timezone ?? config('app.timezone');
    }
}
