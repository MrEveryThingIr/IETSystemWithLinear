# Personal Planner demo seed

This local/testing seed turns the bootstrap superadmin into a realistic single-person Planner demo.

## Run

```bash
php artisan migrate:fresh --seed
```

Login:

- email: `test@example.com`
- password: `password`

The seed uses the user's configured timezone and places activity around the day the seed runs, so Today/List/Calendar views are useful immediately.

## What is represented

The demo intentionally exercises the Planner as if it were the whole product:

- a daily morning routine with a mixed completion history;
- a bad-habit replacement routine that has been paused;
- a focus block that is currently in progress;
- a weekly reflection/review routine;
- groceries/meal preparation with prerequisites and expected-vs-actual spending;
- a finite walking challenge that has been completed;
- an early-rising experiment that was deliberately cancelled;
- a missed one-time task that remains scheduled after its execution window passed.

Together those scenarios cover:

- Plan states: active, paused, completed, cancelled;
- Occurrence states: scheduled, in progress, completed, skipped, cancelled;
- schedule kinds: once, daily, weekly, selected dates;
- readiness prerequisites;
- reminder offsets;
- execution windows;
- expected expenses and actual Planner expense observations;
- past, current, and future calendar density;
- a passed-but-unresolved scheduled item;
- safe seeder re-runs without duplicate demo plans.

## Deliberate boundary

The demo does **not** pretend that "completed" means "perfect".

Planner currently records execution state. A later improvement should model outcome/achievement separately, for example:

- completion state: scheduled / in progress / completed / skipped / cancelled;
- achievement or quality observation: optional 0–100 or rubric-based result;
- purpose progress: derived from the chosen measurement strategy rather than inferred from a checkbox.

That separation avoids making "75% done" ambiguous between partial execution, quality, and long-term goal progress.

The seed also does not introduce Contracts, Settlement, Accounting truth, credential storage, or a generic Tool registry. Those should remain independent capabilities and be composed only when a concrete workflow requires them.
