---
paths:
  - scripts/crop-brand-assets.php
---

# Scripts

## App icon is vector master, not sheet crop
`app-icon.png` (1024) is the sheet-3 variation D odometer (thin teal arc, thin white needle, three small dots) drawn larger in the square — do not restyle it into a thicker lockup-like mark. Strokes stay thin. Keep ~13% navy above the arc so iOS does not turn it into a top border. Platform icons scale the 1024 master. Hot reload does not update home-screen icons; uninstall/reinstall.

## App icon: lockup odometer, navy above the arc
Draw the icon as the lockup mark (thin teal stroke, needle pointing into the dial). Use smooth ellipse strokes, not stacked imagearc. Leave ~16% navy above the arc so iOS does not show a teal top border. Do not shrink the mark to 'fix' the border.
