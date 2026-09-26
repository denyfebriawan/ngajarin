<?php

namespace App\Demo;

use App\Enums\BookingStatus;
use App\Enums\Role;
use App\Models\AvailabilityRule;
use App\Models\Booking;
use App\Models\Subject;
use App\Models\Tenant;
use App\Models\TimeOff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use LogicException;

/**
 * The demo workspace visitors can try: a small tutoring center with two teachers, five students
 * and lessons from four weeks ago to two weeks ahead.
 *
 * reset() deletes it, along with everything visitors did in it, and builds it again dated from
 * today, so the demo never goes stale. It runs nightly (see routes/console.php) and from the
 * database seeder. Lessons follow a fixed pattern instead of random data, so the demo looks the
 * same every day and never breaks the no-double-booking constraints.
 *
 * @phpstan-type Period array{CarbonImmutable, CarbonImmutable}
 * @phpstan-type DemoTeacher array{
 *     user: User,
 *     subjects: list<Subject>,
 *     hours: list<array{int, string, string}>,
 *     away: list<Period>,
 * }
 */
final class DemoWorkspace
{
    // Every demo account's email ends with this, and reset() deletes all such users.
    // `.test` is reserved for testing, so no real inbox can have this address.
    public const EMAIL_DOMAIN = 'demo.ngajarin.test';

    public const TUTOR_EMAIL = 'tutor@'.self::EMAIL_DOMAIN;

    public const STUDENT_EMAIL = 'student@'.self::EMAIL_DOMAIN;

    // Every demo account has this password; it is shown on the site and in the README.
    public const PASSWORD = 'password';

    public const SLUG = 'demo';

    private const TIMEZONE = 'Asia/Jakarta';

    /**
     * Delete the demo workspace and accounts, then build them again from today.
     */
    public function reset(): Tenant
    {
        // One transaction: visitors see the old demo or the new one, never half of each.
        return DB::transaction(function (): Tenant {
            $this->deleteExisting();

            return $this->build(CarbonImmutable::now(self::TIMEZONE)->startOfDay());
        });
    }

    private function deleteExisting(): void
    {
        $demo = Tenant::query()->where('slug', self::SLUG)->first();
        $owner = $demo?->users()->wherePivot('role', Role::Owner)->first();

        // Never delete a real customer's workspace that happens to use the demo's address.
        if ($demo !== null && ($owner === null || ! $owner->isDemo())) {
            throw new LogicException('The workspace "'.self::SLUG.'" does not belong to the demo, so it was not deleted.');
        }

        // The demo workspace, plus any workspace a visitor created while logged in as a demo
        // account. The foreign keys cascade to their memberships, subjects, hours, time off and
        // lessons.
        Tenant::query()
            ->whereHas('users', fn ($users) => $users
                ->where('tenant_user.role', Role::Owner)
                ->where('users.email', 'like', '%@'.self::EMAIL_DOMAIN))
            ->delete();

        // Also removes the memberships and lessons demo accounts have in other workspaces.
        User::query()->where('email', 'like', '%@'.self::EMAIL_DOMAIN)->delete();
    }

    private function build(CarbonImmutable $today): Tenant
    {
        $tenant = Tenant::query()->create([
            'name' => 'Cerdas Learning Center',
            'slug' => self::SLUG,
            'timezone' => self::TIMEZONE,
        ]);

        // Hashing is slow on purpose, so hash the shared password once for all seven accounts.
        $password = Hash::make(self::PASSWORD);

        $budi = $this->member($tenant, 'Budi Santoso', self::TUTOR_EMAIL, Role::Owner, $password);
        $ani = $this->member($tenant, 'Ani Wijaya', 'ani@'.self::EMAIL_DOMAIN, Role::Tutor, $password);

        // The demo student comes first: the first lesson from today on is always theirs.
        $students = [
            $this->member($tenant, 'Siti Rahmawati', self::STUDENT_EMAIL, Role::Student, $password),
            $this->member($tenant, 'Rizky Pratama', 'rizky@'.self::EMAIL_DOMAIN, Role::Student, $password),
            $this->member($tenant, 'Dewi Lestari', 'dewi@'.self::EMAIL_DOMAIN, Role::Student, $password),
            $this->member($tenant, 'Andi Kurniawan', 'andi@'.self::EMAIL_DOMAIN, Role::Student, $password),
            $this->member($tenant, 'Putri Maharani', 'putri@'.self::EMAIL_DOMAIN, Role::Student, $password),
        ];

        $mathSmp = $this->subject($tenant, 'Math (SMP)', 'Algebra, geometry and exam practice for junior high school.', 60, 100_000, [$budi]);
        $mathSma = $this->subject($tenant, 'Math (SMA)', 'Calculus, trigonometry and UTBK problem solving.', 90, 150_000, [$budi]);
        $physics = $this->subject($tenant, 'Physics (SMA)', 'Mechanics, electricity and waves, with lots of worked problems.', 90, 150_000, [$budi, $ani]);
        $english = $this->subject($tenant, 'English Conversation', 'Speaking practice for school, IELTS or work.', 60, 120_000, [$ani]);

        // Ani is away for two days next week, so her booking page skips them.
        $leave = $today->addDays(8);

        /** @var list<DemoTeacher> $teachers */
        $teachers = [
            [
                'user' => $budi,
                'subjects' => [$mathSmp, $mathSma, $physics],
                // [ISO weekday, from, to]: weekday afternoons after school, Saturday mornings.
                'hours' => [[1, '15:00', '20:00'], [2, '15:00', '20:00'], [3, '15:00', '20:00'], [4, '15:00', '20:00'], [5, '15:00', '20:00'], [6, '09:00', '13:00']],
                'away' => [],
            ],
            [
                'user' => $ani,
                'subjects' => [$physics, $english],
                'hours' => [[2, '13:00', '18:00'], [4, '13:00', '18:00'], [6, '10:00', '15:00'], [7, '09:00', '12:00']],
                'away' => [[$leave, $leave->addDays(2)]],
            ],
        ];

        foreach ($teachers as $teacher) {
            foreach ($teacher['hours'] as [$weekday, $from, $to]) {
                AvailabilityRule::query()->forceCreate([
                    'tenant_id' => $tenant->id,
                    'user_id' => $teacher['user']->id,
                    'weekday' => $weekday,
                    'starts_at' => $from,
                    'ends_at' => $to,
                ]);
            }

            foreach ($teacher['away'] as [$from, $to]) {
                TimeOff::query()->forceCreate([
                    'tenant_id' => $tenant->id,
                    'user_id' => $teacher['user']->id,
                    'starts_at' => $from,
                    'ends_at' => $to,
                    'reason' => 'Family event',
                ]);
            }
        }

        $this->lessons($tenant, $today, $teachers, $students);

        return $tenant;
    }

    private function member(Tenant $tenant, string $name, string $email, Role $role, string $passwordHash): User
    {
        // forceCreate: email_verified_at isn't fillable, and demo accounts must count as verified.
        $user = User::query()->forceCreate([
            'name' => $name,
            'email' => $email,
            'email_verified_at' => now(),
            // Already hashed; the `hashed` cast recognises that and doesn't hash it again.
            'password' => $passwordHash,
        ]);

        $tenant->addMember($user, $role);

        return $user;
    }

    /**
     * @param  list<User>  $teachers
     */
    private function subject(Tenant $tenant, string $name, string $description, int $minutes, int $price, array $teachers): Subject
    {
        $subject = Subject::query()->forceCreate([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'description' => $description,
            'duration_minutes' => $minutes,
            'price' => $price,
        ]);

        $subject->syncTeachers(array_map(fn (User $teacher): int => $teacher->id, $teachers));

        return $subject;
    }

    /**
     * Lessons from four weeks ago to two weeks ahead, back to back inside each teacher's hours.
     * The past is busy, with a gap every third slot and some cancellations; from today on there
     * is one lesson on every other day, so the booking page still has plenty of free times.
     *
     * @param  list<DemoTeacher>  $teachers
     * @param  list<User>  $students
     */
    private function lessons(Tenant $tenant, CarbonImmutable $today, array $teachers, array $students): void
    {
        // The lessons each student already has, so no student is booked twice at the same time.
        /** @var array<int, list<Period>> $busy */
        $busy = [];
        $slots = 0;
        $booked = 0;

        for ($offset = -28; $offset <= 14; $offset++) {
            $date = $today->addDays($offset);

            if ($offset === 0) {
                // Restart the student rotation, so the first upcoming lesson goes to the demo student.
                $booked = 0;
            }

            foreach ($teachers as $index => $teacher) {
                foreach ($teacher['hours'] as [$weekday, $from, $to]) {
                    if ($weekday !== $date->isoWeekday()) {
                        continue;
                    }

                    $start = $date->setTimeFromTimeString($from);
                    $blockEnd = $date->setTimeFromTimeString($to);

                    for ($slot = 0; ; $slot++) {
                        // Rotate through the teacher's subjects, one per slot.
                        $subject = $teacher['subjects'][$slots % count($teacher['subjects'])];
                        $end = $start->addMinutes($subject->duration_minutes);

                        if ($end > $blockEnd) {
                            break;
                        }

                        $wanted = $offset < 0
                            ? $slot % 3 !== 2
                            : $slot === 0 && ($offset + $index) % 2 === 0;

                        $student = $wanted && ! $this->overlapsAny($teacher['away'], $start, $end)
                            ? $this->freeStudent($students, $busy, $booked, $start, $end)
                            : null;

                        if ($student !== null) {
                            $cancelled = $offset < 0 && $booked % 7 === 3;

                            Booking::query()->forceCreate([
                                'tenant_id' => $tenant->id,
                                'subject_id' => $subject->id,
                                'teacher_id' => $teacher['user']->id,
                                'student_id' => $student->id,
                                'starts_at' => $start,
                                'ends_at' => $end,
                                'price' => $subject->price,
                                'status' => $cancelled ? BookingStatus::Cancelled : BookingStatus::Confirmed,
                                'cancelled_at' => $cancelled ? $start->subDay() : null,
                            ]);

                            $busy[$student->id][] = [$start, $end];
                            $booked++;
                        }

                        $slots++;
                        $start = $end;
                    }
                }
            }
        }
    }

    /**
     * The first student with no lesson between $start and $end, trying them in order from
     * position $from in the list (wrapping around), or null if all of them are busy.
     *
     * @param  list<User>  $students
     * @param  array<int, list<Period>>  $busy
     */
    private function freeStudent(array $students, array $busy, int $from, CarbonImmutable $start, CarbonImmutable $end): ?User
    {
        for ($i = 0; $i < count($students); $i++) {
            $student = $students[($from + $i) % count($students)];

            if (! $this->overlapsAny($busy[$student->id] ?? [], $start, $end)) {
                return $student;
            }
        }

        return null;
    }

    /**
     * @param  list<Period>  $periods
     */
    private function overlapsAny(array $periods, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        foreach ($periods as [$from, $to]) {
            // End-exclusive, like the Postgres ranges: one lesson may start as another ends.
            if ($start < $to && $end > $from) {
                return true;
            }
        }

        return false;
    }
}
