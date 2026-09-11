---
paths:
  - app/Models/Actor.php
---

# Models

## Protect stable Actor identity and history
Actor.user_id is immutable in ordinary model and UI workflows. Generic Actor administration creates accountless Actors only; Actors are archived with reason/administrator instead of physically deleted. Exceptional relinking requires a future dedicated audited recovery action.
