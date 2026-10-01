---
paths:
  - 'resources/views/vehicles/entry/**'
  - 'app/Http/Controllers/Web/Concerns/*Vehicle*'
  - 'app/Support/Vehicle/VehicleEntryFlow.php'
  - 'app/Services/Vehicle/VehicleOwnershipService.php'
---

# Vehicles Entry

## One "Adicionar veículo" wizard for Proprietário and Lojista
There is a single vehicle entry wizard (resources/views/vehicles/entry) driven by App\Support\Vehicle\VehicleEntryFlow::for(Portal): Documento → Conferir → (Procuração → Análise da equipe) → Capas. The CRLV-e reading decides between new vehicle, claim and power of attorney. GET /usuario/veiculos/vincular and /garagem/estoque/vincular only redirect to /novo; the POST routes stay as internal endpoints. Links that send someone back to the wizard (e.g. "Reenviar procuração") use the create route, not the claim route.

## CRLV-e reading lives only in crlv_verification
The parsed CRLV-e stays in the crlv_verification session entry, checked by crlv_verification_token. Do not copy it into consignment_pending or other keys: with SESSION_DRIVER=cookie the browser silently drops anything above 4 KB. Step 3 (Capas) uses x-ui.image-cropper for 16:9 and 9:16. x-terms-scroll-accept stays at the end of the form that records the vehicle, right before the submit button marked data-terms-submit.

## Claiming an existing vehicle needs proof
The CRLV-e reader only reads the PDF text (no signature or QR check), so VehicleOwnershipService::claimExisting requires the CRLV-e to carry the full chassis equal to the registered one; a vehicle without chassis needs the CRV to match, and RENAVAM alone is refused. Taking a vehicle from another current owner also needs the CRLV-e owner CPF/CNPJ to be the account's document, for Proprietário accounts too (resolveClaimOwnershipType; VehicleEntryFlow::ownershipFor with the claimed vehicle anticipates it); otherwise the claim goes to the power of attorney and the owner keeps the car. An old link of the claimant (sold and bought back, or consignment) is reused, and the dispossessed owners get VehicleClaimedByAnotherAccountNotification by e-mail. New-vehicle registration keeps resolveOwnershipType.
