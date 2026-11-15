# Plano: internacionalização para os EUA (app e portal)

Status: **rascunho para revisão** — nada implementado. Decisões em aberto na seção 7.  
Data: 2026-11-15  
Escopo: backend (API + portais web) e app Flutter (`vehicle_maintenance_frontend`).

O histórico já é do chassi (VIN), não do dono, e isso é o que torna a exportação viável. O que muda nos
EUA é tudo ao redor do VIN: placa, documento, prova de posse, unidade do odômetro, endereço, telefone,
leis de privacidade e mensagens, e o idioma de todas as telas.

As afirmações legais abaixo são levantamento técnico, não parecer. Validar com advogado nos EUA antes do
lançamento.

---

## 1. Arquitetura proposta: um código, duas instâncias

Recomendação: **mesmo repositório, duas instalações** (BR e US), cada uma com banco, domínio e
`.env` próprios (`APP_MARKET=br|us`).

Por quê, e não uma instância com `market` em cada linha:

- Veículo não cruza mercado na prática; não há ganho em ver os dois no mesmo banco.
- Dados de brasileiros ficam no Brasil e dados de americanos nos EUA (LGPD × leis estaduais dos EUA),
  sem filtro por linha para errar.
- Evita migrar em produção `unique(license_plate)` para `unique(country, state, plate)` no banco BR.
- Locale, fuso padrão, catálogo, intervalos de manutenção, agendamentos, rotas e textos legais viram
  **configuração da instância**, não `if ($market === ...)` espalhado.

O que continua por registro: a **jurisdição da placa** no veículo (estado nos EUA) e `locale`/`timezone`
no usuário (há 4+ fusos nos EUA).

Módulos brasileiros ficam atrás de uma interface por mercado e simplesmente não são registrados na
instância US:

| Módulo BR | Onde | Na instância US |
| --- | --- | --- |
| Importação de CRLV-e | `app/Services/Crlv/*`, `app/Rules/CrlvPdfFile.php` | Desligado; substituído por decodificação de VIN (vPIC) |
| NF-e XML / DANFE | `app/Services/Invoice/NfeXmlParser.php`, `NfeDanfeParser.php` | Desligado; nota vira anexo sem leitura (PDF/foto) |
| CEP / ViaCEP | `resources/js/workshop-address-cep.js`, `frontend/lib/.../cep_service.dart` | ZIP sem autocomplete |
| CNPJ / CPF | `app/Rules/ValidCnpj.php`, `DocumentMask`, `users.document`, `AuthController.php:144` | Não coletar CPF/SSN; oficina com licença estadual quando houver |
| UF | `app/Support/BrazilianStates.php` | Lista de estados US (50 + DC) |
| Lojista / consignação | `.ai/rules/consignment.md` (depende de CRLV-e e CPF/CNPJ) | **Fora do escopo US** |
| Prospecção pela Receita | `.ai/rules/outreach.md`, `WorkshopProspect` | **Fora do escopo US** |

---

## 2. Placa, documento e posse nos EUA

### Placa

- Emitida pelo DMV de cada estado (o nome varia: DMV, BMV, MVD, RMV, DOL). Formato varia por estado.
- Única só **dentro do estado e do tipo de placa**: `ABC1234` pode existir no TX e na FL ao mesmo tempo.
- Na maioria dos estados **a placa segue o dono**: na venda, o vendedor tira a placa e põe no carro
  seguinte. Em alguns (CA, MN, HI, AK; opcional em DE) fica com o veículo. Vanity plates migram entre
  carros. Tratar como regra por estado, não como exceção.

Consequências no modelo:

- Placa passa a ser **(estado, placa)** em `vehicles` e `vehicle_plates`; a mesma placa pode aparecer em
  vários veículos ao longo do tempo.
- Busca principal **pelo VIN**; placa + estado vira atalho secundário.
- Hoje a web **recusa qualquer placa não brasileira**:
  `app/Http/Controllers/Web/Concerns/RegistersVehicleWithOwnership.php` (regex Mercosul, mensagem
  "formato ABC1D23 ou ABC1234"), e `resources/js/ui/command.js` detecta "cara de placa" Mercosul.
- `StoreVehicleRequest` / `UpdateVehicleRequest` usam `unique:vehicles,license_plate` global.

### Documentos

- Não existe RENAVAM nem CRLV-e. Existem o **Certificate of Title** (propriedade, emitido pelo estado,
  equivalente ao CRV) e o **registration card** (registro com renovação anual/bienal, fica no porta-luvas).
- Formato varia por estado → não há parser equivalente ao do CRLV-e.
- Substituto prático do preenchimento automático: **NHTSA vPIC** (gratuita), que decodifica o VIN em
  marca, modelo, ano e motor.

### Prova de posse

Hoje: placa + RENAVAM (`app/Http/Requests/Api/V1/LinkVehicleRequest.php`).

Nos EUA o **title number não serve** como equivalente do RENAVAM:

- Carro financiado (a maioria dos novos) tem o título com o banco ou eletrônico (ELT); o dono não tem o
  número.
- O número muda a cada reemissão e aparece em anúncios de venda.
- Guardar VIN + title number é vetor de pedido fraudulento de segunda via em alguns estados.

Proposta: cadastro do dono **sem gate de verificação** e um selo opcional **"Verified owner"** por envio
do **registration card** (VIN igual + nome compatível com a conta), com análise. O gate só fica onde
protege algo concreto (ex.: anexos em OS de veículo sem dono).

### VIN

`app/Rules/Chassis.php` aceita chassi legado antes de 1990 e não confere o dígito verificador. Na
instância US:

- Corte do legado em **1981** (17 caracteres obrigatórios desde o MY1981, 49 CFR 565).
- **Dígito verificador** (posição 9) conferido como **aviso**, não bloqueio (importados).
- Mensagens do `$fail` saem do código para os arquivos de tradução.

### Odômetro

- Leitura em **milhas**. Guardar o número como aparece no painel + `odometer_unit` **no veículo** (um
  odômetro tem uma unidade a vida toda), sem converter.
- Tudo que assume km precisa da unidade:
  - Teto de plausibilidade: `app/Services/Vehicle/VehicleMileageService.php` (50.000 de base + 100.000/ano).
  - Intervalos: `config/maintenance_intervals.php` (10.000 km de intervalo, 2.000 de aviso, 13.000 km/ano;
    EUA ≈ 5.000 mi e 13.500 mi/ano) e `lead_kilometers max:50000`.
  - `VehicleEstimatedKmService`, variáveis `{{estimated_km}}` / `{{next_due_km}}` do
    `WorkshopMessageTemplateRenderer`, lembretes de km.
- A lei federal de declaração de odômetro (49 U.S.C. §32705 / 49 CFR 580) é de **transferência de
  propriedade**; não obriga o app, mas é referência de formato.

### Fora do MVP

- **NMVTIS** (histórico de títulos: salvage, flood): custo por consulta e restrições de uso; e ao consumir
  dado de DMV passa a valer o DPPA.
- **Recalls NHTSA**: bom recurso, mas não diferencia o lançamento.

---

## 3. Leis e riscos que mexem no produto

| Tema | O que vale | Impacto |
| --- | --- | --- |
| Privacidade desde o dia 1 | **CalOPPA** (política publicada, resposta a Do Not Track), sinal **GPC** (obrigatório em CA, CO, CT…), leis estaduais de **notificação de vazamento** (50 estados) | Privacy Policy US; respeitar GPC no `gtag` (`components/analytics.blade.php` hoje concede `analytics_storage` por padrão) |
| CCPA/CPRA e leis estaduais similares | Só ao atingir limiar (US$25M de receita, 100 mil consumidores ou 50% da receita com venda de dados) | Planejar "Do Not Sell or Share" e direitos de acesso; exclusão de conta já existe |
| Consulta pública | Placa + histórico de placas + fotos são "associáveis a uma pessoa"; com placa seguindo o dono, o histórico de placas liga carros a alguém | **Maior risco de produto.** Ver seção 5, F1 |
| DPPA (18 U.S.C. §2721) | Só se consumirmos dados de DMV (NMVTIS/provedores) | Nenhum agora |
| E-mail | **CAN-SPAM**: endereço físico, descadastro | Já temos opt-out (`WorkshopMessageOptOutController`, `email_suppressions`, List-Unsubscribe); falta endereço físico US no rodapé |
| SMS | **TCPA** (consentimento expresso por escrito p/ marketing, STOP/HELP) e horários estaduais (ex.: FL) | **Não existe SMS no projeto.** As mensagens da oficina saem por e-mail + sino + push; WhatsApp é só o link `wa.me` do convite (`CustomerInviteMessage.php`, prefixo 55 fixo). Nos EUA o convite tende a virar SMS — aí entra TCPA |
| Garantia | **Magnuson-Moss**: fabricante não pode negar garantia por manutenção fora da concessionária, com comprovante. **Right to Repair** (MA 2020, ME 2023, REPAIR Act federal em tramitação) | Argumento de venda para donos e oficinas independentes |
| Oficinas | A **maioria dos estados não licencia** oficina; os que licenciam têm busca pública (CA BAR ARD, FL DACS, NY DMV). Leis de reparo (CA, FL) exigem orçamento escrito e nota detalhada | Cadastro: licença estadual **quando existir** + razão social + endereço + aprovação manual (como hoje). EIN opcional (sem consulta pública; sole proprietors usam SSN) |
| Dados sensíveis | SSN, driver's license number, title number | **Não coletar**; documento enviado para "Verified owner" criptografado e com retenção curta |
| Alegações | **FTC** fiscaliza "verificado"/"certificado" | Selo diz exatamente o que verifica (o painel de contestação já vai nessa linha) |
| Acessibilidade | **ADA / WCAG 2.1 AA** (ações judiciais comuns contra sites) | Revisão de acessibilidade do portal antes do lançamento |
| Negócio | Entidade nos EUA (LLC/C-corp), sales tax de SaaS por estado | Ver F4 |

---

## 4. Glossário pt-BR → en-US

| pt-BR | en-US | Nota |
| --- | --- | --- |
| placa | license plate | "tag" no Sul |
| chassi | VIN | |
| RENAVAM | — | sem equivalente; não substituir por title number |
| CRV | Certificate of Title | |
| CRLV-e | Vehicle Registration (card) | |
| Detran | DMV | varia: BMV, MVD, RMV, DOL |
| licenciamento | registration renewal | |
| vistoria | state inspection | segurança/emissões, varia por estado |
| IPVA | registration fees / vehicle property tax | |
| oficina | auto repair shop | |
| ordem de serviço (OS) | repair order (RO) | |
| nota fiscal | invoice / receipt | |
| revisão | scheduled maintenance / service | |
| quilometragem | mileage / odometer reading | |
| selo da oficina | Shop-verified | |
| manutenção declarada | Owner-reported | |
| ficha do veículo | vehicle history | |
| contestação | dispute | |
| garantia | warranty | |
| convite | invite | |
| loja / lojista | dealer / dealership | fora do escopo US |
| consignação | consignment | fora do escopo US |
| CEP | ZIP code | 5 ou 5+4 |
| bairro | — | não existe; remover do formulário |
| logradouro + número | address line 1 / line 2 | |
| UF | state | |
| CNPJ | EIN | opcional |
| CPF | — | não coletar |
| motorização | engine | ex.: 2.0L Turbo |
| flex / álcool | flex-fuel / ethanol (E85) | |

Formatos: datas MM/DD/YYYY, moeda $1,234.56, milhas, telefone +1 (E.164), fuso por usuário, papel
**Letter** nos PDFs.

---

## 5. Fases

### F0 — Fundação (sem mudança visível no BR)

- `APP_MARKET` e config por instância: locale, fuso padrão, catálogo, intervalos, rotas, textos legais.
- Interface por mercado para os módulos da tabela da seção 1; instância BR registra os atuais.
- `users.locale` e `users.timezone`.
- **Códigos de erro estáveis na API** e remoção das comparações de texto no Flutter
  (`lib/views/auth/login_page.dart:125` compara "Este portal", `two_factor_challenge_page.dart:88`
  compara "Invalid authentication code"). Pré-requisito para localizar a API sem quebrar o login.

### F1 — Destraves pequenos e dados do veículo (MVP)

- Placa com estado; unicidade por (estado, placa) na instância US; tirar a regex Mercosul da web e o
  detector de `command.js` na US; busca por VIN e por placa + estado.
- **Catálogo de marcas/modelos por mercado** — a lacuna mais cara. Hoje é fechado nos dois clientes
  (`database/data/vehicle_catalog.php`, `VehicleCatalogService`, regra em `.ai/rules/vehicles.md`): 22
  marcas com viés BR, sem Subaru, Tesla, Ram, GMC, Dodge, Mazda, Lexus, Acura, Lincoln, Buick, Cadillac.
  Opção: catálogo US alimentado pela vPIC, ou catálogo aberto com sugestão pelo VIN.
- Decodificação de VIN pela vPIC no cadastro.
- `odometer_unit` no veículo + constantes da seção 2 por mercado.
- Regra de VIN US (corte 1981, dígito verificador como aviso).
- Prova de posse por mercado (BR: placa + RENAVAM; US: sem gate + "Verified owner").
- **Consulta pública US**: exigir login na busca por placa (ou devolver só contagem/selo), mascarar
  `plate_history` e não expor fotos — `PublicVehicleSearchResource.php` (manda `license_plate` e
  `plate_history` sem máscara) e `MaintenanceRedactor` (`publicLookup` mostra fotos a qualquer visitante).
- Nota fiscal por foto: `app/Rules/InvoiceFile.php` só aceita `pdf` e `xml`; incluir jpg/png/heic.
  PDF não reconhecido já é anexado com aviso (`InvoiceUploadProcessor`).
- `currency` em `invoices` (hoje `total_amount`/`unit_price` sem moeda).
- Endereço: `workshops.cep char(8)` + `size:8` não cabe ZIP+4; `neighborhood` obrigatório; `street` +
  `number` separados; `users.country default 'Brasil'`; `AddressGeocoder` com `'br'` fixo (Nominatim já
  aceita `us`, sem necessidade de Google Places/Smarty).
- Telefone E.164: hoje `digits_between:10,11` / `regex:/^\d{10,11}$/`
  (`StoreWorkshopSignupRequest`, `UpdateAccountRequest`), `ContactMask::phone` no formato BR.

### F2 — Extração de textos (em paralelo com F1, não na frente)

Maior custo mecânico. Inventário:

- Backend: 246 blades sem nenhum `__()`; ~571 literais em `app/` (≈80 em Notifications/Mail, 19 Requests
  com `messages()`, `$fail` em Rules, labels de enums como `ServiceCategory` e `WorkshopPlan`,
  `config/maintenance_intervals.labels`), 14 títulos de push, `number_format(…, ',', '.')` em ~12 pontos,
  `d/m/Y` em ~14 arquivos de `app/` e ~37 views, pluralização manual
  (`VehicleMaintenanceHistory.php`).
- PDFs: DomPDF em `a4` (`config/dompdf.php:164`) → `letter` na US.
- Rotas em pt-BR (`/buscar-veiculo`, `/oficina`, `/termos`, `/privacidade`, `/descadastrar`,
  `/v/{code}/sou-o-dono`…), `<html lang="pt-BR">` nos layouts, blog/landing/sitemap; sem hreflang.
  Na instância US, rotas em inglês por config.
- `LandingSampleVehicle` com placa e chassi brasileiros.
- **Testes**: ~293 `assertSee` com textos pt-BR. Estratégia antes de começar: suíte BR continua forçando
  `pt_BR` (`phpunit.xml`), e testes novos da US rodam com `APP_MARKET=us`/`en`. Orçar esse trabalho.
- Reescrever `.ai/rules/locale.md` (hoje proíbe inglês no código web).
- Flutter: `flutter_localizations` + `gen-l10n` (ARB), `supportedLocales` no `MaterialApp`, enviar
  `Accept-Language`, `intl` na versão exigida pelo SDK (hoje `^0.18.1`), trocar `DateFormat`/
  `NumberFormat` fixos em `pt_BR` (ex.: `NumberFormat.simpleCurrency(locale: 'pt_BR')` em
  `vehicle_maintenance_timeline.dart`). ~163 `Text('…')` literais em 99 arquivos.
- Store listings en-US (App Store e Play).

Dá para dividir por área (dono, oficina, admin, e-mails/PDF, app) e tocar em paralelo.

### F3 — Oficinas e canais US

- Cadastro de oficina: licença estadual quando existir + aprovação manual; EIN opcional; sem CNPJ.
- Convite ao dono por SMS (substituindo o `wa.me` com prefixo 55) com consentimento TCPA, STOP/HELP e
  horários; avaliar se a RevisaLog vira "sender" ao automatizar.
- Agendamentos no fuso do destinatário: hoje `maintenance:check-km-reminders dailyAt('08:00')` e
  `workshop-reviews:remind 09:00` com app em UTC; janela de outreach fixada em `America/Sao_Paulo`
  (`routes/console.php`).

### F4 — Legal e lançamento

- Terms of Service e Privacy Policy US (CalOPPA, GPC, cláusula de arbitragem, endereço físico no rodapé
  de e-mails).
- Revisão de acessibilidade (WCAG 2.1 AA).
- Domínio, marca e entidade nos EUA.
- Piloto em 1–2 estados.

### Épico à parte — Cobrança

Não existe gateway hoje (o plano free/pro da oficina é trocado pelo admin; nada de Stripe/IAP no backend
nem no app). Cobrança nos EUA é projeto novo: Stripe, sales tax de SaaS por estado, e regras de compra
dentro do app nas lojas.

---

## 6. O que já ajuda

- VIN como identidade única; placa e RENAVAM já são nullable.
- Histórico de placas com datas (`vehicle_plates`) — base para (estado, placa).
- Oficina já cria veículo só pelo chassi (`WorkshopVehicleRegistrar`, "Veículo sem placa").
- Exclusão de conta (`DELETE /api/v1/me`), opt-out de mensagens e supressão de e-mail.
- Mascaramento de chassi/RENAVAM para quem não é dono.
- Sign in with Apple já presente no app.

---

## 7. Decisões em aberto

1. **Marca, domínio e instâncias**: mesma marca RevisaLog em `.com` ou outra? Aceitamos "um código,
   duas instâncias"? (Recomendado.)
2. **Primeiro público nos EUA**: donos ou oficinas? Define se a primeira entrega é cadastro do veículo
   (VIN, catálogo, placa + estado) ou portal da oficina.
3. **Estados piloto**: quais 1–2? CA e FL são candidatos por terem registro público de oficinas.
4. **Prova de posse**: cadastro sem gate + "Verified owner" opcional pelo registration card, ou
   comprovação obrigatória desde o início?
