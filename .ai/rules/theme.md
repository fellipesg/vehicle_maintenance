---
paths:
  - 'resources/css/provenance.css,frontend/lib/theme/provenance.dart'
---

# Theme

## Contrato visual de procedência
Tokens prov.ink (#0F766E selo / #92400E declarada), prov.surface, rail sólido vs tracejado, marcador logo vs anel. Termos: Selo da oficina (verificada), Declarada pelo proprietário/lojista (não verificada). Espelhar em Blade (.prov-*), provenance-ui.js e Flutter provenance.dart.

Resumo de procedência (capa PDF): contador compacto (`N com selo · M declarada(s)`) + linha de pontos `.prov-dot` (10px, gap 4px) — disco teal cheio = selo, anel tracejado âmbar = declarada. PDF capa: um ponto por manutenção com data mm/aa, 12 por linha. Flutter `ProvenanceStrip`: só os pontos (até 16 + `+N`); o texto `N com selo · M declarada(s)` não aparece na ficha — o filtro Todas/Selo/Declaradas fica abaixo da timeline. Não usar mais a barra segmentada `.prov-strip*`.

Dompdf: evitar SVG de QR se não renderizar; usar data URI PNG ou só código mono no PDF.
