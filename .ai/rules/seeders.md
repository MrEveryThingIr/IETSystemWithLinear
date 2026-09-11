---
paths:
  - 'database/seeders/**'
---

# Seeders

## Keep default demonstration seeding repeatable
DatabaseSeeder includes the construction-project demonstration dataset and must remain safe to rerun without deleting or duplicating existing application data. Demo records use stable project names and example.test account emails; extend the scenario through ConstructionProjectSeeder rather than adding destructive migrate:fresh assumptions.
