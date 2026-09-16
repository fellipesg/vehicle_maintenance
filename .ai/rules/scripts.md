---
paths:
  - scripts/crop-brand-assets.php
---

# Scripts

## App icon is variation D, not the D label
App icon is variation D on sheet-3-abcd.jpg: crop(790, 250, 200, 200). Never use (772, 108, 248, 248) — that captures the D caption and only the top of the odometer. generateAppIcons must inset the mark (~0.84) on #0B1C2C so the iOS squircle does not clip it. Android adaptive `ic_launcher_foreground` uses `innerAppMark()` (drops the teal card frame) so the launcher mask does not show a double ring. Stacked lockups on sheet-3 start at y=132, not 108, to skip the A/B captions. Hot reload does not update home-screen icons; uninstall/reinstall the sim app.
