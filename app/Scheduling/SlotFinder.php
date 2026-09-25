<?php

namespace App\Scheduling;

use App\Enums\BookingStatus;
use App\Models\AvailabilityRule;
use App\Models\Booking;
use App\Models\Subject;
use App\Models\Tenant;
use App\Models\TimeOff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

/**
 * Works out when a lesson can start: the teacher's weekly hours turned into real moments in the
 * workspace's timezone, minus their time off and every lesson that makes the teacher (or the
 * student) busy.
 *
 * It filters by the given tenant itself instead of relying on the tenant global scope, because
 * the public booking page is visited by people who aren't members of the workspace yet.
 */
final class SlotFinder
{
    // Lessons may start every 30 minutes from the start of each block of weekly hours.
    public const STEP_MINUTES = 30;

    // Booking closes this long before a lesson starts.
    public const MIN_NOTICE_HOURS = 2;

    // Lessons can be booked up to this far ahead.
    public const HORIZON_DAYS = 28;

    /**
     * Every moment a lesson of this subject with this teacher can start, earliest first, in UTC.
     * Pass the student to also skip times when they already have a lesson.
     *
     * @return list<CarbonImmutable>
     */
    public function starts(Tenant $tenant, User $teacher, Subject $subject, ?User $student = null): array
    {
        $now = CarbonImmutable::now();
        $earliest = $now->addHours(self::MIN_NOTICE_HOURS);
        $latest = $now->addDays(self::HORIZON_DAYS);
        $duration = $subject->duration_minutes;

        $rulesByWeekday = AvailabilityRule::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $teacher->id)
            ->orderBy('starts_at')
            ->get()
            ->groupBy('weekday');

        $busy = $this->busyPeriods($tenant, $teacher, $student, $earliest, $latest->addMinutes($duration));

        $starts = [];

        // Walk the calendar in the workspace's timezone: "Monday" means Monday where the teacher is.
        $days = CarbonPeriod::create(
            $earliest->setTimezone($tenant->timezone)->startOfDay(),
            $latest->setTimezone($tenant->timezone)->startOfDay(),
        );

        foreach ($days as $day) {
            $date = $day->toDateString();

            foreach ($rulesByWeekday->get($day->isoWeekday(), []) as $rule) {
                // "Monday 09:00" + this date + the workspace's timezone = one exact moment.
                $blockStart = CarbonImmutable::parse("$date {$rule->starts_at}", $tenant->timezone);
                $blockEnd = CarbonImmutable::parse("$date {$rule->ends_at}", $tenant->timezone);

                for ($start = $blockStart; $start->addMinutes($duration) <= $blockEnd; $start = $start->addMinutes(self::STEP_MINUTES)) {
                    $end = $start->addMinutes($duration);

                    if ($start < $earliest || $start > $latest || $this->overlapsAny($start, $end, $busy)) {
                        continue;
                    }

                    $starts[] = $start->utc();
                }
            }
        }

        sort($starts);

        return $starts;
    }

    /**
     * Everything that makes the teacher, or the student, unavailable between $from and $until.
     *
     * @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function busyPeriods(Tenant $tenant, User $teacher, ?User $student, CarbonImmutable $from, CarbonImmutable $until): array
    {
        // Time off is per workspace, like weekly hours.
        $timeOff = TimeOff::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $teacher->id)
            ->where('starts_at', '<', $until)
            ->where('ends_at', '>', $from)
            ->get(['starts_at', 'ends_at']);

        // Lessons count across every workspace, matching the no-double-booking constraints: a
        // person can't be in two lessons at once anywhere, whether teaching or learning in them.
        // Only their times are read.
        $people = array_values(array_filter([$teacher->id, $student?->id]));

        $lessons = Booking::withoutGlobalScopes()
            ->where('status', BookingStatus::Confirmed)
            ->where(fn ($query) => $query->whereIn('teacher_id', $people)->orWhereIn('student_id', $people))
            ->where('starts_at', '<', $until)
            ->where('ends_at', '>', $from)
            ->get(['starts_at', 'ends_at']);

        return $timeOff->concat($lessons)
            ->map(fn (TimeOff|Booking $period) => [$period->starts_at, $period->ends_at])
            ->values()
            ->all();
    }

    /**
     * Whether [$start, $end) overlaps any of the busy periods (all half-open).
     *
     * @param  array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>  $busy
     */
    private function overlapsAny(CarbonImmutable $start, CarbonImmutable $end, array $busy): bool
    {
        foreach ($busy as [$busyStart, $busyEnd]) {
            if ($start < $busyEnd && $busyStart < $end) {
                return true;
            }
        }

        return false;
    }
}
