---
paths:
  - 'frontend/lib/widgets/provenance/**'
---

# Provenance

## Flutter strip: dots only, no count caption
ProvenanceStrip on vehicle detail shows only the teal/amber dots (plus overflow +N). Do not render the visible caption "N com selo · M declarada(s)" — counts stay in Semantics for a11y. Filters Todas/Selo/Declaradas sit below the timeline, not next to the strip.

## Provenance dots on timeline card only
ProvenanceStrip dots (teal/amber) belong on the Linha do tempo card, immediately above ProvenanceFilterBar (8px gap). Never render them in the vehicle identity block (plate/chassis). Filters are not duplicated on the strip (showFilterChips: false); counts stay in Semantics only.
