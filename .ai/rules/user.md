---
paths:
  - 'app/Services/User/**'
---

# User

## Account deletion anonymizes; VIN history stays
Never User::delete() for in-app account deletion. maintenances.user_id cascades and would wipe VIN history registered by that user, including records other owners still need. Anonymize PII, revoke tokens/FCM, detach only that user's vehicle pivots, and leave vehicles plus all maintenances on the chassis untouched.
