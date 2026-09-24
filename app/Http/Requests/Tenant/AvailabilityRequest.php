<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * A teacher's whole week of availability, which replaces what they had before.
 */
class AvailabilityRequest extends FormRequest
{
    // A time on a quarter hour, e.g. "09:00" or "13:45".
    private const QUARTER_HOUR = 'regex:/^([01]\d|2[0-3]):(00|15|30|45)$/';

    private const WEEKDAYS = [1 => 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // `present` allows an empty list, which clears the week.
            'blocks' => ['present', 'array', 'max:50'],
            'blocks.*.weekday' => ['required', 'integer', 'between:1,7'],
            'blocks.*.starts_at' => ['required', 'string', self::QUARTER_HOUR],
            'blocks.*.ends_at' => ['required', 'string', self::QUARTER_HOUR, 'after:blocks.*.starts_at'],
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
            'blocks.*.starts_at.regex' => 'Use a time on the quarter hour, like 09:00 or 13:45.',
            'blocks.*.ends_at.regex' => 'Use a time on the quarter hour, like 09:00 or 13:45.',
            'blocks.*.ends_at.after' => 'The end must be after the start.',
        ];
    }

    /**
     * Checks that need the whole list: no two blocks on the same day may overlap.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return; // Only compare blocks that are individually valid.
                }

                /** @var array<int, array{weekday: int, starts_at: string, ends_at: string}> $blocks */
                $blocks = $this->input('blocks', []);

                foreach ($blocks as $i => $block) {
                    foreach ($blocks as $j => $other) {
                        // "HH:MM" strings compare correctly as text, so no parsing is needed.
                        if ($j < $i
                            && (int) $block['weekday'] === (int) $other['weekday']
                            && $block['starts_at'] < $other['ends_at']
                            && $other['starts_at'] < $block['ends_at']) {
                            $validator->errors()->add(
                                "blocks.$i.starts_at",
                                'This overlaps other hours on '.self::WEEKDAYS[(int) $block['weekday']].'.',
                            );
                        }
                    }
                }
            },
        ];
    }

    /**
     * The validated blocks, ready to save.
     *
     * @return list<array{weekday: int, starts_at: string, ends_at: string}>
     */
    public function blocks(): array
    {
        /** @var array<int, array{weekday: int|string, starts_at: string, ends_at: string}> $blocks */
        $blocks = $this->validated('blocks');

        return array_values(array_map(fn (array $block) => [
            'weekday' => (int) $block['weekday'],
            'starts_at' => $block['starts_at'],
            'ends_at' => $block['ends_at'],
        ], $blocks));
    }
}
