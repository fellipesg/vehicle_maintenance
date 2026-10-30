---
paths:
  - 'resources/views/vehicles/entry/**'
  - 'app/Http/Controllers/Web/Concerns/*Vehicle*'
  - 'app/Support/Vehicle/VehicleEntryFlow.php'
  - 'app/Services/Vehicle/VehicleOwnershipService.php'
  - 'app/Services/Crlv/**'
---

# Vehicles Entry

## One "Adicionar veículo" wizard for Proprietário and Lojista
There is a single vehicle entry wizard (resources/views/vehicles/entry) driven by App\Support\Vehicle\VehicleEntryFlow::for(Portal): Documento → Conferir → (Procuração → Análise da equipe) → Capas. The CRLV-e reading decides between new vehicle, claim and power of attorney. GET /usuario/veiculos/vincular and /garagem/estoque/vincular only redirect to /novo; the POST routes stay as internal endpoints. Links that send someone back to the wizard (e.g. "Reenviar procuração") use the create route, not the claim route.

## CRLV-e reading lives only in crlv_verification
The parsed CRLV-e stays in the crlv_verification session entry, checked by crlv_verification_token. Do not copy it into consignment_pending or other keys: with SESSION_DRIVER=cookie the browser silently drops anything above 4 KB. Step 3 (Capas) uses x-ui.image-cropper for 16:9 and 9:16. x-terms-scroll-accept stays at the end of the form that records the vehicle, right before the submit button marked data-terms-submit.

## Claiming an existing vehicle needs proof
The CRLV-e reader only reads the PDF text (no signature or QR check), so VehicleOwnershipService::claimExisting requires the CRLV-e to carry the full chassis equal to the registered one; a vehicle without chassis needs the CRV to match, and RENAVAM alone is refused. Taking a vehicle from another current owner also needs the CRLV-e owner CPF/CNPJ to be the account's document, for Proprietário accounts too (resolveClaimOwnershipType; VehicleEntryFlow::ownershipFor with the claimed vehicle anticipates it); otherwise the claim goes to the power of attorney and the owner keeps the car. An old link of the claimant (sold and bought back, or consignment) is reused, and the dispossessed owners get VehicleClaimedByAnotherAccountNotification by e-mail. New-vehicle registration keeps resolveOwnershipType.

## Chegada do proprietário a um carro da oficina
Sem dono, placa e RENAVAM, o veículo é assumido na web só pelo CRLV-e: o chassi inteiro precisa bater, e placa e RENAVAM passam a vir do documento (claimExisting falha se outro veículo já tem esse RENAVAM ou placa). No app, o cadastro manual com o mesmo chassi chama claimUnclaimedWorkshopVehicle: vínculo sem verificação (ownership_verified_at nulo). Não existe auto-adoção: claimExisting e o cadastro nunca mudam o tenant das OS pendentes; quem decide é o proprietário em "Registros de oficinas". Ao chegar, WorkshopRecordsArrivalNotifier avisa (database + e-mail + FCM, type workshop_records_pending, vehicle_id e count) e a web leva à tela de registros.

## Falha de leitura do CRLV-e tem limite
As rotas POST de upload de CRLV-e (importar, vincular e importar na edição, nos dois portais) usam
`throttle:uploads` (10/min por conta, App\Providers\AppServiceProvider). As telas de conferência são
GET, só leem `crlv_verification` da sessão e não reprocessam o PDF: ficam fora do balde para não
gastar a cota de quem recarrega a página.

`CrlvImportFailureReporter` limita o aviso por duas portas: `Cache::add('crlv-failure:'.sha1(classe +
mensagem normalizada + id da conta))` deixa passar a primeira ocorrência de cada falha por conta por
hora, e `RateLimiter::attempt('crlv-failure-global:'...)` põe um teto de `GLOBAL_ALERTS_PER_WINDOW`
avisos da mesma falha na hora somando todas as contas. Sem a primeira, um loop de PDFs inválidos
enchia a caixa do suporte; sem a segunda, um layout novo de DETRAN que chega a centenas de pessoas
fazia o mesmo, porque a conta entra na chave. O balde por conta ignora o nome do arquivo e a rota de
origem de propósito — renomear o PDF ou trocar de tela não é falha nova.

A ordem das duas portas é essa de propósito: a janela da conta primeiro, o teto global depois e só
para quem passou por ela — assim a conta que reenvia o mesmo PDF em loop gasta um crédito global, não
um por tentativa, e não fecha o aviso das outras. Aviso barrado pelo teto **devolve** a janela da
conta (`reopenWindow`) e sai no log com `GLOBAL_CAP_LOG_MESSAGE`: sem isso, a conta suprimida no
minuto em que o teto encheu ficava calada o resto da hora, mesmo depois de o teto liberar, e uma
falha real de CRLV-e sumia sem aviso nenhum. Despacho de e-mail que falha devolve a janela pelo mesmo
motivo, senão um tropeço da fila na primeira ocorrência calava o aviso por uma hora. O teto global
nunca é devolvido — é um crédito em dez, e a rota já está limitada.

Fora da janela de propósito fica o `Log::warning('CRLV-e não lido.')`, que é local, barato e é o que
mostra quantas vezes a pessoa tentou. O e-mail vai pela fila (`Mail::to()->queue`) e o contexto não
leva o e-mail de quem enviou nem o nome que o cliente deu ao arquivo (pode trazer nome ou CPF —
LGPD, `.ai/rules/models.md`): vão o id da conta, a extensão, o tamanho e um hash curto do conteúdo
(`hash_arquivo`), que junta envios do mesmo PDF. `CrlvImportFailureMail::BLOCKED_CONTEXT_KEYS` corta
`arquivo` e `usuario_email` na borda que manda o aviso para fora, mesmo que alguém volte a pô-los no
contexto. O que falta ao suporte é o layout do DETRAN, não a pessoa.
