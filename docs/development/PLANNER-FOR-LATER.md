# Planner — "For later" (unscheduled items)

**Architecture.** A "For later" item is an ordinary `Plan` with `metadata.kind = 'later'` and **no schedule rule**. No migration was needed: the timing engine (materialization, start windows, expiry, Today, calendar, reminders, outbox) works on `PlanOccurrence` rows, and an unscheduled Plan has none. `Plan::isForLater()` is the single definition (active, kind `later`, no rule).

**Unscheduled means unscheduled.** No date, time, rule, occurrence or reminder is created; nothing is overdue, started or expired; it never appears on Today or in calendar cells. The Plan's `timezone` column (NOT NULL) holds the creator's default but nothing reads it until a rule exists.

**Create.** Planner → "For later" (or New → For later): title and optional notes; labels/colour/importance in the usual Details step. Steps are What → Details → Review (no When).

**Schedule later.** "Schedule" opens the normal wizard on the same Plan (`planner.create?schedule={uuid}`), starting at When. Saving adds a schedule rule to that same Plan, sets its timezone to the one chosen, and replaces kind `later` with `task`. Title, notes, labels, colour, importance and Content links are untouched; from then on the normal engine governs it. Refused (404) once it is no longer For later.

**Edit.** Title and notes on the Plan page while it is For later.

Tests: `tests/Feature/PlannerForLaterTest.php`.
