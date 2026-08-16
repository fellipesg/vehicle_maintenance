---
paths:
  - resources/views/components/vehicle-cover.blade.php
---

# Components

## Do not crop vehicle cover photos
Cover photos are often portrait (phone). Never force a fixed aspect (16:9, 3:4) with object-cover on listing cards or detail heroes — that crops the car. Hero and card show the full photo with object-contain; only tiny dashboard thumbs may use object-cover.
