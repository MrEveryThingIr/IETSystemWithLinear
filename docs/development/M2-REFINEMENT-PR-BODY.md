## M2 refinement: category-aware composer, field library and semantic field intelligence

Draft. Base `feat/m2-adaptive-intention-composer` (PR #86); depends on M1 PR #85.

**What:** minimal Composer with a searchable, localized field library; typed exact/at-least/at-most/between/one-of constraints; unambiguous vehicle and other fields; optional notes and helpful validation; governed community suggestions with superadmin review and immutable versions; shared taxonomy; personal groups; composition templates; shared published Need/Offer cards; the Composer is now the only editor for Needs and Offers (legacy links redirect); My Items status filters and saved templates/groups.

**Migrations (additive):** `2026_11_11_000000_create_intent_field_library`, `2026_11_12_000000_add_constraint_columns_and_field_taxonomy`. Both already ran on the owner's Laragon database and were rehearsed on populated MySQL.

**Evidence:** full suite 1170 tests, Pint and PHPStan clean; CI green on the head; browser journeys recorded in `docs/development/M2-ADAPTIVE-COMPOSER-HANDOFF.md`.

**Not in scope / not claimed:** M3. Not merged; owner acceptance and merge are separate decisions.
