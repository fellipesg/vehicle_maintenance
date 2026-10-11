---
paths:
  - 'resources/views/legal/**'
  - 'app/Support/LegalDocument.php'
  - 'app/Http/Controllers/Web/LegalController.php'
---

# Legal pages

## Section ids come from LegalDocument
Termos de uso and Política de privacidade are parsed by App\Support\LegalDocument: each numbered heading ("7. Seus direitos") becomes a section with a stable id taken from the title slug without the number (#seus-direitos), suffixed -2, -3 only on a repeated title. Do not put the number into the id or renumber anchors by hand: links shared to a section must survive a new section being inserted above it. The table of contents (collapsible on phones, sticky column from lg) reads LegalDocument::sections(), and the text sits in the .doc-content reading column (56ch, 17–18px, line-height 1.8).

## Bump de versão não força novo aceite
terms_version e privacy_version só aparecem nas páginas, na API e em user_vehicles.terms_version no aceite de cada veículo novo; trocar a versão não pede novo aceite de ninguém. Ao incluir cláusula nova, as seções seguintes mudam de número mas os ids (#seus-direitos) não.
