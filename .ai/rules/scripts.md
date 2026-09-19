---
paths:
  - scripts/crop-brand-assets.php
---

# Scripts

## App icon is vector master, not sheet crop
`app-icon.png` (1024) is the sheet-3 variation D odometer (thin teal arc, thin white needle, three small dots) drawn larger in the square — do not restyle it into a thicker lockup-like mark. Strokes stay thin. Keep ~13% navy above the arc so iOS does not turn it into a top border. Platform icons scale the 1024 master. Hot reload does not update home-screen icons; uninstall/reinstall.

## App icon: lockup odometer, navy above the arc
Draw the icon as the lockup mark (thin teal stroke, needle pointing into the dial). Use smooth ellipse strokes, not stacked imagearc. Leave ~16% navy above the arc so iOS does not show a teal top border. Do not shrink the mark to 'fix' the border.

## OG preview fills square without left bar
OG share image is 1200x630 navy with the stacked lockup (no left bar) filling ~90% of the center 630px square so WhatsApp crops do not show a tiny card or a vertical rule. Publish as og-preview.png to bust CDN/WhatsApp cache; do not reuse the cached og-image.png URL.
