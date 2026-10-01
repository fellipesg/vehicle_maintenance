---
paths:
  - 'frontend/lib/views/vehicles/**,resources/views/user/vehicles/**'
---

# User Vehicles

## duas capas 16:9 e 9:16
Cadastro pede capa paisagem (celular deitado, 16:9) e retrato (celular em pé, 9:16). Hero largo / desktop usa paisagem; telas <768px, avatares e PDF usam retrato. Sempre cropper antes do upload: na web, <x-ui.image-cropper name="cover" aspect="16:9"> e <x-ui.image-cropper name="cover_portrait" aspect="9:16"> (JPEG de no máximo 1920 px, qualidade 0,85; Cancelar mantém a foto anterior daquela orientação), no passo Capas do assistente e em Editar veículo do Proprietário e do Lojista.
