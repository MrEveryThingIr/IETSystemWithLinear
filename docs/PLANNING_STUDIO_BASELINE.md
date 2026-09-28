# Planning Studio Baseline

## Purpose

This branch is an experience-first selective assembly.

It does **not** rebuild previously developed capabilities. Mature branches remain source libraries. The runtime admits only the minimum layers needed to establish a clean desktop/workspace and a deliberately small personal Planning Studio.

## Runtime profile

The default profile is:

```text
planning_baseline
```

Included runtime layers:

1. application/platform infrastructure;
2. account identity, authentication and email verification;
3. invitation-based platform access and authorized access administration;
4. clean Profile: account image/identity, interface language, and system-wide temporal/display preferences;
5. personal Context provision required by Planner authority;
6. basic Planning Studio;
7. shared profile-aware temporal/calendar infrastructure;
8. the existing fractal calendar navigation;
9. the selectively admitted paper-like Personal Money surface;
10. the per-User encrypted Private Vault.

The historical integrated application remains available as the `full` profile for regression/source-library verification. PHPUnit explicitly uses that profile so previously developed capabilities are not silently invalidated by the smaller product surface.

## Deliberately excluded from the baseline experience

The source tree may contain these mature capabilities, but this runtime does not expose them:

- Needs / Offers / Intents;
- Relationships and Proposals;
- Contracts and Commitments;
- Groups / GroupSpace collaboration;
- Content Studio and Evidence workflows;
- Submission / Evaluation;
- shared economic obligations / settlement and advanced financial workflows;
- Planner prerequisites and readiness configuration;
- Planner expense estimates and actual expense capture;
- Planner participant assignment;
- recurring schedule authoring;
- reminder authoring;
- Domain Blueprint / Journey wiring;
- System Manual and System Map;
- ambient date/time rail.

These may be re-admitted later as independent capabilities or explicit seams after browser acceptance.

## Basic planning semantics

The first Planning Studio intentionally has only two timing shapes.

### Fixed time

Use when the intended activity belongs to an exact interval.

Required planning data:

```text
title
date
start time
end time
```

Optional:

```text
category
notes
```

### Flexible day

Use when completion within a day matters but an exact clock time does not.

Required planning data:

```text
title
date
```

Optional:

```text
category
notes
```

Internally this uses the existing occurrence kernel with a day-wide execution window. The UI does not pretend that midnight is the intended start time.

### Attention

Attention is orthogonal to timing:

- **Needs full focus** is the safe default for a timed Plan. Two full-focus baseline intervals cannot overlap.
- **Can run in the background** may overlap full-focus work, such as listening to audio while working.

This distinction is not inferred from category. It is authored explicitly and preserved by Repeat time window.

## Execution meaning

`Done` means only:

> execution of this occurrence ended.

It does **not** yet mean:

- the intended purpose was achieved;
- quality was high;
- the result was successful;
- a goal advanced by a particular percentage.

Outcome, quality and purpose-progress semantics are separate future capabilities. This prevents one overloaded completion field from carrying several different truths.

## Calendar

The accepted fractal calendar remains a primary Planning Studio view:

```text
year
→ month
→ day
→ hour
→ 60 / 30 / 15 / 5 / 1 minute partitions
```

Calendar cells remain compact and accumulated:

- year: count per month;
- month: total count plus fixed/flexible counts per day;
- day: flexible-day items separately, fixed items accumulated by hour;
- hour: fixed items accumulated by selected minute quantum;
- select a populated quantum to reveal its items.

Filters apply before accumulation:

- free-text search over title, notes and category;
- timing kind: all / fixed / flexible day;
- category.

## Clean Profile and date/time presentation

The baseline Profile is deliberately system-focused. It contains only:

- profile image;
- username/email identity;
- display name;
- interface language;
- timezone behavior and timezone;
- primary calendar: automatic, Gregorian, Persian/Jalali, or Umm al-Qura;
- date display style: long, medium, or numeric;
- time display style: 24-hour or 12-hour;
- optional Gregorian equivalent when a non-Gregorian calendar is primary.

Skills, interests, needs/offers, biography, public-profile workflows, and other domain concepts remain excluded.

The temporal kernel is authoritative. Date pickers—including Planning Studio tools—reuse the profile-aware calendar picker rather than native Gregorian-only date/month controls. Repeat-by-month and same-day-of-month/year projection follow the user's selected calendar boundaries, not Gregorian boundaries hidden underneath the UI.

Stored dates remain canonical Gregorian civil dates/UTC instants as appropriate; display and calendar selection do not rewrite underlying historical data.

## Typography and surface hierarchy

The baseline uses a bilingual font stack:

- Inter for Latin text;
- Vazirmatn for Persian/Arabic text;
- robust system fallbacks when web fonts are unavailable.

The visual baseline uses a light page canvas, distinct white working surfaces, clearer borders, and stronger selected/active states. Contrast should clarify hierarchy without turning every block into a heavy card.

## Planning Studio navigation and time-window projection tool

Planning Studio keeps its primary views as a compact horizontal navigation:

- Today;
- Next 30 days;
- Calendar.

`New item` remains a separate primary action.

Optional capabilities live in a deliberately narrow **Tools rail** beside the Studio. The rail does not replace the primary Planner navigation and should consume as little calendar width as practical.

The first optional tool is **Repeat time window**. Opening it presents a strongly distinguished, collapsible inline panel rather than a modal. The operation has an explicit Cancel action and can be collapsed without leaving the Studio.

Any non-cancelled baseline once-plan can be used as a source, including a future Plan that has never started. Completion or success is not required.

The first time-fractal projection modes are:

- following days;
- same weekday across future weeks;
- same day-of-month across future months;
- same month/day across future years;
- every future day in a chosen month;
- individually selected future dates.

The source occurrence supplies the lower-level time window. For a fixed-time source, its hour and duration are preserved. For a flexible-day source, the copies remain flexible-day items.

Generated items remain independent once-plans and preserve provenance back to the source Plan and occurrence. This gives useful recurrence-like engineering without reintroducing a large recurrence editor into the baseline.

## Admission rule for the next capability

Do not expand the baseline because a field already exists in another branch.

For each next addition:

```text
one real scenario
→ identify the missing atomic capability
→ recover the strongest existing implementation
→ simplify/refactor its boundary if needed
→ expose only the minimum UI
→ test
→ browser accept
→ keep or revise
```

## Browser acceptance checklist

1. Login/invitation/verification still behave normally.
2. Workspace is visually quiet and exposes only admitted baseline capabilities.
3. Create a fixed-time item with only title/date/start/end.
4. Create a flexible-day item without choosing a clock time.
5. Edit an item and confirm the replacement schedule is visible without duplicate active occurrences.
6. Today and Next 30 Days remain compact.
7. Calendar drills Year → Month → Day → Hour → minute quantum.
8. Month/day/hour cells show accumulated counts rather than expanding rows.
9. Search, timing and category filters change calendar accumulation.
10. Profile exposes image/account identity/language/temporal display preferences without unrelated Profile domains.
11. Set Persian/Jalali calendar and confirm Planner + Repeat date pickers and month projection follow Jalali boundaries.
12. Change date format, 12/24-hour format, and Gregorian-equivalent preference; confirm shared temporal presentation follows it.
13. Check Persian typography and mixed Persian/English text at desktop and mobile widths.
14. Personal Money and Private Vault are reachable, while unadmitted routes such as Groups/Contracts/Content Studio remain unavailable in the baseline profile.
15. A fixed full-focus item cannot overlap another full-focus item; a background-compatible item may overlap it.
16. Calendar minute/quarter/hour cells reflect the complete interval of a timed item, not only its start timestamp.
17. Calendar display tools can switch between details and a quiet visual map and color by Plan, category, or attention.
