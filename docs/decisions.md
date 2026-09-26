# Build log and decisions

How each feature was built, slice by slice, and the decisions made along the way. The rules
that still apply to new code live in `CLAUDE.md`; this file is the history behind them.

## Setup

Installed from the Laravel React starter kit, npm packages fixed (see the npm gotchas in
`CLAUDE.md`), starter-kit setup files cleaned up, pushed to `github.com/denyfebriawan/ngajarin`.
Moved from SQLite to PostgreSQL (`ngajarin` database). Tests switched from PHPUnit style to Pest
and run against a separate `ngajarin_test` Postgres database, locally and in CI.

## Feature 1: auth, tenants, roles

Slices: (1) tenants, memberships and roles schema, (2) tenant routing and the membership
middleware, (3) `BelongsToTenant` global scope for tenant-owned data, (4) Policies/Gates per
role, (5) onboarding UI ("create your workspace", tenant pages in the sidebar).

Decisions:

- Tutors create a workspace and become its owner; students join a workspace later through its
  public booking link (feature 3).
- Users see "workspace", code says "tenant".
- `TenantController@store` turns a unique-index violation on the slug into a validation error:
  the same technique feature 3 uses for the double-booking constraint.

## Feature 2: subjects and availability (PR #1)

Decisions: subjects belong to the workspace (the owner manages them; every member can view);
tutors are linked to the subjects they teach through a pivot; owners can teach too; prices are
whole rupiah integers; durations are minutes (15–480, multiples of 5, also CHECK-constrained).

1. **Subjects CRUD:** `TenantPolicy::manageSubjects`, `SubjectController` (resource routes, index
   open to all members), `SubjectForm`, `lib/format.ts`.
2. **Who teaches what:** `subject_teacher` pivot with composite foreign keys, so Postgres only
   allows same-workspace links and removes them when a member leaves. The owner picks teachers
   on the subject form; the subject list eager-loads teachers (a test guards against N+1).
3. **Weekly availability and a workspace timezone**, split into 3a (data: `tenants.timezone`,
   `availability_rules` with its exclusion constraint, the `btree_gist` migration) and 3b (the
   page: `TenantPolicy::teach`, `AvailabilityController`, `useForm` editing the whole week and
   saving it in one transaction; `AvailabilityRequest` checks overlaps within the submission,
   the constraint catches concurrent saves).
4. **Time off:** `time_off` table with `time_off_no_overlap`; whole local days, end exclusive;
   `TimeOffPolicy::delete` (own entries only).

While building time off, dates came out 7 hours off: Laravel sends dates to the database as
`Y-m-d H:i:s` without the offset, and the local Windows Postgres defaulted to Asia/Bangkok.
Fixed once at the connection level (see "Time zones" in `CLAUDE.md`) instead of per model.

## Feature 3: booking flow (PR #4)

Decisions: a public booking page per workspace (`/t/{slug}/book`); booking requires signing in,
and the first booking makes the user a student of that workspace; bookings are confirmed
immediately (payment may add a pending step in feature 6); lessons start every 30 minutes inside
a teacher's hours, at least 2 hours and at most 4 weeks ahead.

1. **Bookings table and constraints:** `period tstzrange` generated column, the two exclusion
   constraints (teacher and student), composite FKs, CHECKs. The constraints have no
   `tenant_id` on purpose: a person in two workspaces still can't be in two lessons at once.
2. **Slot finder:** `App\Scheduling\SlotFinder`.
3. **Booking page**, split into 3a (backend: public routes, `StoreBookingRequest`, the
   transaction and the 23P01 handling) and 3b (`pages/booking/create.tsx`, `public-layout.tsx`;
   the shared `Auth.user` type became `User | null`).
4. **Lesson lists and cancelling:** `LessonController@index`, `BookingPolicy::cancel`. After
   booking, students are redirected to their lessons.

## Branding and UI polish (PRs #6, #9)

Teal brand tokens, the "N" logo and favicons, `tw-animate-css` animations, the landing page
(which replaced the starter kit's Laravel page), Plus Jakarta Sans headings, the booking-page
mock in the hero, Open Graph tags, and the workspace overview with stats from one query using
Postgres `count(*) FILTER (WHERE ...)`.

## Email verification switch (PR #7)

`AUTH_VERIFY_EMAIL` lets production run without verification until real email sending works.

## Flaky test fix (PR #8)

`SubjectFactory` picks a random 30/45/60/90-minute lesson. A 90-minute subject didn't fit a
one-hour availability block, so a `LessonsTest` test failed about one run in four. Rule since
then: tests set every factory value they depend on.

## Student experience (PR #10)

Decided with the developer: Ngajarin is "Calendly for tutors", not a marketplace, so there is no
public directory of tutors (it could be a later feature). The sidebar separates "My tutors"
from "Teaching"; the home page shows upcoming lessons across all workspaces.

## Demo workspace (PRs #11, #12, commit 8fa2a2c)

Slices: (1) demo data (`DemoWorkspace::reset()`, `demo:reset`, nightly schedule), (2) one-click
demo logins and protecting the shared demo accounts, (3) the README for recruiters and reviewing
engineers. The demo tests build the workspace on each weekday (a dataset) to prove the fixed
lesson pattern never breaks the booking constraints and always leaves free times.

## Deployment

Azure VM, scripted in `deploy/`. The first deploy of feature 2 broke because
`wayfinder:generate` read the route cache left by the previous deploy and missed new routes;
`deploy.sh` now runs `optimize:clear` before the frontend build.
