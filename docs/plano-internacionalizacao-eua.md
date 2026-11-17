# Plano: internacionalização para os EUA (app e portal)

Status: **rascunho para revisão** — nada implementado. Decisões em aberto na seção 7.  
Data: 2026-11-15 · revisado em 2026-11-16, em duas passadas, com a revisão incorporada no corpo
(seções 1, 2, 3, 5 e 7).  
Afirmações de código reconferidas no `main` em d44ee31; onde o número mudou, está corrigido aqui.  
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
- Os dois conjuntos de dados ficam separados sem filtro por linha para errar: uma consulta esquecida não
  mistura titulares de regimes diferentes (LGPD × leis estaduais dos EUA).
- Locale, fuso padrão, catálogo, intervalos de manutenção, agendamentos, rotas e textos legais viram
  **configuração da instância**, não `if ($market === ...)` espalhado.

O que **não** é argumento, e saiu desta lista: "dados de brasileiros ficam no Brasil". Hoje não ficam — o
disco `r2` usa `region => 'auto'` e o bucket legado tem `us-east-2` como default
(`config/filesystems.php:99,126`), então foto de carro brasileiro já sai do país. E a LGPD não exige
armazenamento local: exige mecanismo de transferência internacional (art. 33). Residência de dados é
decisão de infraestrutura por instância — região do banco, do bucket e do deploy — e, se for requisito,
vira item explícito de F0, não subproduto desta seção.

Também saiu "evita migrar `unique(license_plate)` para `unique(country, state, plate)` no banco BR": ver
os custos abaixo. O banco BR ganha uma migração de índice pequena, e em troca o schema não diverge.

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

### Dois custos que a escolha cria

**Um schema por mercado saindo de uma pasta de migrações só.** O BR precisa de placa única; a US **não
pode ter**. Na maioria dos estados a placa segue o dono: sai do carro vendido e vai para o seguinte. Se
ninguém atualizou o carro antigo no app, o dono do novo recebe "placa já cadastrada" —
`VehiclePlateHistoryService::changePlate` encerra a placa só no próprio veículo, não a libera em outro — e
qualquer pessoa consegue "reservar" uma placa que não é dela. Nos EUA a placa é atributo pesquisável com
datas; identidade é só o VIN.

Resolver isso com `if (config('app.market') === 'us')` dentro de migração é o mesmo `if ($market)` que esta
seção quer evitar, no lugar menos reversível do sistema. Três saídas:

| Saída | O que custa |
| --- | --- |
| Migração condicional por mercado | O schema real passa a depender do `.env`: o `migrate:fresh` de cada dev reproduz o mercado dele, e a suíte cobre um dos dois |
| **Mesmo schema, índice único parcial** | Coluna `plate_jurisdiction` + `unique(license_plate) WHERE plate_jurisdiction = 'BR'`. Uma migração, igual nos dois bancos, nenhum `if`: no banco BR toda linha é BR e a unicidade continua valendo; no banco US nenhuma linha entra no índice |
| Duas pastas (comum + por mercado) | Explícito; exige caminho de migração por instância e disciplina para não duplicar o comum |

Recomendada: a segunda. O comportamento muda pelo **dado**, não pelo código. O repositório já tem o
padrão, com o tratamento por driver para SQLite e Postgres, em
`2026_09_13_150614_create_maintenance_warranties_table.php:32`. No banco BR custa uma migração — coluna
nova com backfill constante, índice parcial criado, índice antigo derrubado —, não migração de dados
sujos. A primeira saída é a que parece mais barata e é a que mais machuca depois.

**O app é um binário; a loja é uma listagem.** Duas instâncias no backend não dizem como o Flutter
escolhe a sua, e localizar a ficha da loja não cria um segundo app. Duas saídas:

- **Dois apps** (bundle ids distintos, cada um apontando para uma instância): ficha, ASO e avaliações
  separadas por mercado; duas publicações por release e dois projetos de push.
- **Um app com seleção de instância** antes do login: uma publicação, mas a URL da API passa a ser estado
  do app, e o token de push, o `Accept-Language` e o cache local têm de seguir a troca.

É decisão de produto, não detalhe de implementação; está na seção 7.

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
  vários veículos ao longo do tempo — e, por dado desatualizado, em dois ao mesmo tempo. Por isso a placa
  americana não é única no banco (seção 1, "Dois custos que a escolha cria").
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
do **registration card** (VIN igual + nome compatível com a conta), com análise.

#### "Sem gate" tem de dizer qual predicado muda

O gate de hoje não olha se o veículo tem dono: olha `user_vehicles.ownership_verified_at`, e quem grava
essa marca é só o CRLV-e (`VehicleOwnershipService.php:462`, `'ownership_verified_at' => $crlv ? now() : null`).
Dela dependem `canDecide` e `canAcceptAttachments` (`MaintenanceOwnerDecisionService.php:78,96`).

Consequência na instância US, sem CRLV-e: **ninguém recebe a marca**, nenhum dono aceita anexo, e
`maintenance:purge-pending-attachments` apaga os anexos pendentes em 90 dias
(`config/maintenance.pending_attachments_retention_days`) — sem erro em tela. Contestar continua
funcionando, e de propósito: `canDispute` não pede a marca.

**O gate não pode simplesmente afrouxar.** Trocar o predicado por "ser dono atual" (como `canDispute`)
entrega as notas e fotos — nota fiscal traz nome e endereço do cliente — a quem cadastrou o carro com um
VIN lido pelo para-brisa. O docblock de `Maintenance::hidesAttachmentsFrom` diz por que o gate existe:
"dono sem comprovação" não vê nada. Gravar `ownership_verified_at` no cadastro tem o mesmo efeito, e ainda
faz a coluna mentir no nome.

**O predicado que funciona sem documento já existe e não é usado: o convite da oficina.** Toda OS sem dono
pode ter um convite em `maintenance_invites`, com `token` único por OS, e o convite por e-mail grava
`email_hash`. Hoje o token não vale nada: abrir `/convite/{token}` só leva ao cadastro
(`Web\MaintenanceInviteController::show`). Proposta para a US: quem chega pelo convite daquela OS — e, no
convite por e-mail, com o e-mail da conta igual ao `email_hash` — fica verificado **para aquele
registro**, e pode decidir sobre ele e aceitar seus anexos. É a oficina como verificadora, registro a
registro, sem documento e sem OCR.

Não pesa sobre quem faz a própria manutenção: o gate só vale para registro de oficina sem dono
(`isOwnerlessRecord`, `owner_status` preenchido); o que o dono declara não passa por ele. Três ressalvas:

- `MaintenanceInviteService::canInvite` exige que o veículo não tenha dono atual. Na US, o convite tem de
  continuar possível com um dono **não verificado** no carro, senão quem ocupa o carro desliga o convite
  (ver o conflito abaixo).
- O convite por WhatsApp não guarda telefone: vale só pelo token, que pode ser repassado. É mais fraco
  que o de e-mail.
- Nos EUA o convite tende a virar SMS, e aí entra a TCPA (F3).

#### Conflito: duas contas, o mesmo VIN

No BR o CRLV-e faz duas coisas: dá a marca de posse e **decide os conflitos**.
`VehicleOwnershipService::claimExisting` só tira o carro de outro dono atual se o CPF/CNPJ do documento
for o da conta (`.ai/rules/vehicles-entry.md`, "Claiming an existing vehicle needs proof"). "Cadastro sem
gate" substitui a primeira função e deixa a segunda sem ninguém.

Na US, quem digita o VIN primeiro fica com o carro — e o VIN se lê do lado de fora. O que essa pessoa
consegue fazer, no código de hoje:

| Ação | Hoje | Efeito na US |
| --- | --- | --- |
| Bloquear o convite da oficina | `canInvite` exige veículo sem dono atual | A oficina não consegue mais convidar o cliente de verdade |
| Contestar o histórico | `canDispute` pede só ser dono atual | Toda OS de oficina do carro pode sair marcada como contestada; a marca aparece em 24 pontos de `app/` e `resources/`, inclusive nas superfícies públicas |
| Esconder ou aceitar registros | `decide()` exige `ownershipVerified` (`MaintenanceOwnerDecisionService.php:201`) | Não consegue — isso segura |

E o dono de verdade, chegando depois, não tem como tomar o carro de volta sem documento. Proposta:

- **"Verified owner" é obrigatório no conflito e opcional no resto.** Uma reivindicação verificada desloca
  um vínculo não verificado, com o aviso que já existe (`VehicleClaimedByAnotherAccountNotification`).
- **Na US, contestar fica com dono verificado ou com quem chegou pelo convite.** Lá ocupar um carro não
  custa nada, e contestar é a única ação que o ocupante tem sobre o histórico dos outros.

Achado lateral: o docblock de `VehicleOwnershipService::claimUnclaimedWorkshopVehicle` diz que o dono não
verificado "pode vincular ou recusar o registro mínimo e ocultá-lo", mas `decide()` recusa tudo isso.
O código está certo e o comentário está velho — justamente na peça de que o fluxo US de posse vai depender.

O que o selo exige além de schema: alguém para analisar os envios, e retenção curta de um documento que
traz nome e endereço — o dado que a seção 3 manda não guardar. Fica gravado o **resultado** da
conferência (quais campos bateram, quem analisou, quando), não o arquivo.

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
| Consulta pública | Placa + histórico de placas + fotos são "associáveis a uma pessoa"; com placa seguindo o dono, o histórico de placas liga carros a alguém. Nos EUA a chave é o **VIN**, legível pelo para-brisa da rua | **Maior risco de produto.** Ver seção 5, F1 |
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

### Antes das fases: o que o piloto precisa provar

As fases abaixo dizem o que construir. Nenhuma diz o que vai ser **aprendido** — e sem isso não há como
decidir continuar ou parar, porque qualquer resultado serve para seguir. Duas consequências de ordem:

- **A cobrança está fora da fila** (épico à parte) e o piloto é F4. Como está, o teste de mercado nasce de
  graça: mede uso, não disposição a pagar. É uma escolha legítima — mas então o critério de sucesso é de
  uso, e no fim do piloto não vale concluir nada sobre receita. Se o que se quer medir é receita, Stripe
  entra antes de F4 e o épico deixa de ser à parte.
- **Definir os números antes de construir.** Mesmo grossos: quantos veículos cadastrados por VIN, quantas
  OS seladas por oficina ativa, quantas oficinas que registram mais de uma vez, em quanto tempo, nos
  estados do piloto — e o que faz **parar**. Escrever isso depois de construir é escrever para justificar
  o que já foi feito.

Preencher esta subseção é pré-requisito de F4, não de F0; decidir o que ela vai medir é pré-requisito de
F0, porque muda a ordem das fases.

### F0 — Fundação (sem mudança visível no BR)

- `APP_MARKET`, config por mercado e interfaces por mercado, com as garantias de que nenhum ponto ficou
  assumindo Brasil — desenho em "Como cada parte do sistema sabe em que mercado está", logo abaixo.
  Instância BR registra os módulos atuais.
- `users.locale` e `users.timezone`.
- **Códigos de erro estáveis na API** e remoção das comparações de texto no Flutter
  (`lib/views/auth/login_page.dart:125` compara "Este portal", `two_factor_challenge_page.dart:88`
  compara "Invalid authentication code"). Pré-requisito para localizar a API sem quebrar o login.
- **Como o schema fica igual nos dois mercados** (seção 1, "Dois custos que a escolha cria"):
  `plate_jurisdiction` e o índice único parcial `WHERE plate_jurisdiction = 'BR'`, para não haver
  migração condicional por mercado. É mudança no banco BR e entra aqui, não em F1.
- **Residência de dados, se for requisito**: região do banco, do bucket e do deploy por instância. Hoje o
  disco `r2` usa `region => 'auto'` e o bucket legado tem `us-east-2` como default.

#### Como cada parte do sistema sabe em que mercado está

Operar dois bancos é a parte fácil. A difícil é cada parte do sistema saber onde está. Hoje não existe
conceito de mercado: nenhum `APP_MARKET` ou equivalente em `config/`, e o Brasil está assumido como
constante — `America/Sao_Paulo` em 5 arquivos, `'pt_BR'` em 7, `'Brasil'` em 8, `wa.me` em 7,
`BrazilianStates` em 6 (em `app/`, `config/`, `routes/`, `resources/views`, `resources/js` e migrações). E
`config/app.php` cai em `pt_BR` quando falta `APP_LOCALE`.

Princípio: **o código não pergunta "estou nos EUA?"; pergunta o valor de que precisa.** O `if` por mercado
existe, num lugar só.

1. **Uma fonte de verdade.** `APP_MARKET` lido uma vez, um enum `Market` e dois arquivos lado a lado,
   `config/markets/br.php` e `config/markets/us.php`, com tudo o que é *valor*: locale, fuso padrão, moeda,
   unidade do odômetro, papel do PDF, formatos de telefone, endereço e data, intervalos de manutenção,
   horários dos agendamentos, nomes de rota, textos legais. O código lê `config('market.currency')`; os
   dois arquivos juntos são o inventário completo do que muda entre os países.
2. **Comportamento diferente vira interface, escolhida num provider.** Placa, VIN, documento, endereço,
   nota fiscal, prova de posse (a tabela de módulos da seção 1). Um `MarketServiceProvider` liga a
   implementação do mercado; o resto do código só conhece a interface.
3. **O que não existe num mercado não é registrado.** Rotas de lojista e consignação, prospecção pela
   Receita, a janela de outreach em `America/Sao_Paulo`, itens do admin: registrados ou não no boot.
   **Não usar o Pennant para isso.** Ele está instalado e é tentador, mas é flag de liberação gradual — por
   escopo, guardada no banco, mutável em tempo de execução. Mercado é jurisdição, constante do deploy;
   misturar os dois abre a porta para "ligar o Brasil" para um usuário americano.
4. **O que sai do banco leva o mercado na linha.** Moeda, jurisdição da placa e unidade do odômetro ficam
   gravadas no registro mesmo com bancos separados. Regra: se o valor viaja (PDF, exportação, API), carrega
   o mercado; se só governa o comportamento da instância, é config.
5. **O app pergunta ao servidor.** Hoje o Flutter fixa o formato brasileiro
   (`NumberFormat.simpleCurrency(locale: 'pt_BR')`). Em vez de dois caminhos no app, a API informa o
   mercado na inicialização (locale, moeda, unidade, formato de placa) e o app monta as telas com isso.

O mecanismo sozinho não basta: o risco são os lugares que continuam assumindo Brasil sem ninguém ver. Por
isso a F0 entrega também as garantias:

- **Suíte rodando duas vezes no CI**, com `APP_MARKET=br` e `APP_MARKET=us` (matriz em
  `.github/workflows/ci.yml`). Teste que só vale para o BR leva `#[Group('market-br')]` e é pulado na
  rodada US; todo o resto passa nas duas. É a garantia mais forte, e é o que dá sentido à conversão de
  asserções da F2.
- **Teste de arquitetura** que varre `app/` e falha com `America/Sao_Paulo`, `'pt_BR'`, `'BRL'`, `wa.me` ou
  `BrazilianStates` fora do módulo BR. Pega a suposição nova no PR, antes de virar dívida.
- **Paridade das configs**: os dois arquivos de mercado com as mesmas chaves — o mesmo teste das chaves de
  tradução.
- **Sem default silencioso**: `APP_MARKET` ausente ou inválido, a aplicação não sobe. Hoje uma instância US
  configurada pela metade subiria em português, sem erro.
- **O banco sabe de que mercado é.** Com schema idêntico, apontar a instância US para o banco BR (um
  `DATABASE_URL` copiado errado) funciona sem erro nenhum — o pior acidente possível, e um risco que só
  existe com duas instâncias. O mercado é gravado no próprio banco, numa linha escrita na criação, e conferido no
  boot e no health check.

### F1 — Destraves pequenos e dados do veículo (MVP)

- Placa com estado (o índice parcial vem de F0: só placa BR é única); tirar a regex Mercosul da web e o
  detector de `command.js` na US; busca por VIN e por placa + estado. As validações
  `unique:vehicles,license_plate` de `StoreVehicleRequest`, `UpdateVehicleRequest` e
  `RegistersVehicleWithOwnership` passam a valer só para placa BR.
- **Catálogo de marcas/modelos por mercado** — menos caro do que parece, e não é a lacuna mais cara.
  Não é fechado no schema nem na validação: marcas e modelos são tabelas (`VehicleBrand`, `VehicleModel`)
  com CRUD de admin (`VehicleBrandController`, `VehicleModelController`), semeadas de
  `database/data/vehicle_catalog.php` via `VehicleCatalogSeeder`, e a API valida
  `'brand' => 'required|string|max:100'` — hoje já aceita qualquer marca. Fechados são o `<select>` do
  form web (`resources/views/user/vehicles/_form.blade.php:51`, que já mantém na lista a marca do
  registro fora do catálogo) e o picker do Flutter. São 22 marcas com viés BR, sem Subaru, Tesla, Ram,
  GMC, Dodge, Mazda, Lexus, Acura, Lincoln, Buick e Cadillac. É trabalho de seeder + admin por mercado,
  não de arquitetura; a lacuna mais cara do plano é a F2. Opção: catálogo US alimentado pela vPIC, ou
  catálogo aberto com sugestão pelo VIN.
- Decodificação de VIN pela vPIC no cadastro.
- `odometer_unit` no veículo + constantes da seção 2 por mercado.
- Regra de VIN US (corte 1981, dígito verificador como aviso).
- Prova de posse por mercado (BR: placa + RENAVAM; US: cadastro sem gate, convite da oficina como
  verificação por registro e "Verified owner" para o conflito — seção 2).
- **Consulta pública US**: o gate é no **identificador**, não na placa. `VehiclePlateSearch::findByIdentifier`
  tenta chassi primeiro, e nos EUA o VIN é a chave principal — e legível pelo para-brisa, da calçada.
  Trancar só a busca por placa deixa aberta justamente a porta que vai ser usada. Exigir login na busca
  por identificador (ou devolver só contagem/selo), mascarar `plate_history` e não expor fotos:
  `PublicVehicleSearchResource.php` manda `license_plate`, `current_plate` e `plate_history` sem máscara,
  e `MaintenanceRedactor` (`$hidePhotos = ! $publicLookup && …`) mostra as fotos públicas a qualquer
  visitante — com URL de `AppStorage::url()`, pública e permanente, não temporária: uma vez vazada, fica.
  **Custo de produto a aceitar por escrito:** se a busca aberta é topo de funil no BR (SEO, quem procura
  uma placa e se cadastra), exigir login na US tira o funil junto. É troca de produto, não linha de
  compliance — e, pela mesma leitura, a exposição de hoje já merece revisão no BR.
- Nota fiscal por foto: `app/Rules/InvoiceFile.php` só aceita `pdf` e `xml`; incluir jpg/png/heic.
  PDF não reconhecido já é anexado com aviso (`InvoiceUploadProcessor`).
- `currency` em `invoices` (hoje `total_amount`/`unit_price` sem moeda).
- Endereço: `workshops.cep char(8)` + `size:8` não cabe ZIP+4; `neighborhood` obrigatório; `street` +
  `number` separados; `users.country default 'Brasil'`; `AddressGeocoder` com `'br'` fixo (Nominatim já
  aceita `us`, sem necessidade de Google Places/Smarty).
- Telefone E.164: hoje `digits_between:10,11` / `regex:/^\d{10,11}$/`
  (`StoreWorkshopSignupRequest`, `UpdateAccountRequest`), `ContactMask::phone` no formato BR.

### F2 — Extração de textos (em paralelo com F1, não na frente)

**A fase mais cara do plano**, e o custo é mecânico, não intelectual. Inventário reconferido:

- Backend: **249 das 249 blades** sem nenhum `__()`, `@lang` ou `trans()` — não é "quase tudo", é tudo —
  e **zero** `__()` em `app/`. Hoje `lang/` só tem o que vem do framework (`auth`, `pagination`,
  `passwords`, `validation`). ~580 literais acentuados em `app/` (≈83 em Notifications/Mail, 50 em
  Requests com `messages()`, `$fail` em Rules, labels de enums como `ServiceCategory` e `WorkshopPlan`,
  `config/maintenance_intervals.labels`), 14 títulos de push, `number_format(…, ',', '.')` em ~12 pontos,
  `d/m/Y` em ~14 arquivos de `app/` e ~37 views, pluralização manual
  (`VehicleMaintenanceHistory.php`).
- PDFs: DomPDF em `a4` (`config/dompdf.php:164`) → `letter` na US.
- Rotas em pt-BR (`/buscar-veiculo`, `/oficina`, `/termos`, `/privacidade`, `/descadastrar`,
  `/v/{code}/sou-o-dono`…), `<html lang="pt-BR">` nos layouts, blog/landing/sitemap; sem hreflang.
  Na instância US, rotas em inglês por config.
- `LandingSampleVehicle` com placa e chassi brasileiros.
- **Testes**: **103 arquivos** com asserção de texto — **861** `assertSee` com string literal (304 com
  acento, inequivocamente pt-BR) e **242** `assertDontSee`. Manter a suíte BR em `pt_BR` (`phpunit.xml`
  força; `.ai/rules/locale.md` registra a armadilha do `<env>` × `<server>`) e escrever os testes novos da
  US em `en` funciona e não quebra nada — mas deixa a instância US **sem cobertura de view nenhuma**: os
  861 `assertSee` descrevem a instância brasileira e nenhum deles roda na americana.
  **Decisão:** ao extrair os textos de uma área, converter as asserções daquela área para
  `assertSee(__('chave'))` no mesmo passo. O mesmo teste passa a valer nos dois locales, os arquivos já
  vão estar abertos, e deixar para depois é fazer a varredura duas vezes. Hoje são 0 `assertSee(__(`.
  **A conversão precisa de uma trava**, senão vira teste que passa sempre: quando a chave não existe, o
  `__()` devolve a própria chave na view e no teste, e a tela mostra `vehicle.plate_label` com a suíte
  verde. O framework instalado tem a trava: `Lang::handleMissingKeysUsing()`, registrado no `TestCase`
  para lançar exceção em chave faltando. Junto, um teste de paridade: `lang/pt_BR` e `lang/en` com as
  mesmas chaves. É isso que faz a rodada `APP_MARKET=us` do CI (F0) cobrir o texto de verdade.
  Orçamento: parte dos 861 `assertSee` confere dado (placa, nome, número), não texto de interface, e não
  precisa de conversão — o custo real fica mais perto dos 304 com acento do que dos 861.
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
   duas instâncias"? (Recomendado.) E, junto: **dois apps** (bundle ids distintos, um por instância) ou
   **um app com seleção de instância** antes do login? O backend com duas instalações não responde isso,
   e um bundle id é um app por loja (seção 1).
2. **Primeiro público nos EUA**: donos ou oficinas? Define se a primeira entrega é cadastro do veículo
   (VIN, catálogo, placa + estado) ou portal da oficina. **Decidir antes de orçar a F1**: oficina
   primeiro usa `WorkshopVehicleRegistrar`, que já cria veículo só com chassi, e tira da primeira entrega
   o catálogo, a regex de placa, a decodificação vPIC e o gate da consulta pública. Dono primeiro traz os
   quatro de volta — é a mesma fase com o dobro do tamanho.
3. **Estados piloto**: quais 1–2? CA e FL são candidatos por terem registro público de oficinas.
4. **Prova de posse**: cadastro sem gate + "Verified owner" opcional pelo registration card, ou
   comprovação obrigatória desde o início? Em qualquer das duas, decidir três coisas (seção 2): **o que
   libera anexo e decisão** na US (hoje é `ownership_verified_at`, que só o CRLV-e grava; a proposta é o
   convite da oficina, por registro); **quem ganha um conflito** pelo mesmo VIN (proposta: "Verified
   owner" obrigatório no conflito); e **quem pode contestar** (proposta: dono verificado ou quem chegou
   pelo convite). Sem a primeira, anexo pendente nos EUA morre na purga de 90 dias.
5. **Critério de sucesso do piloto e lugar da cobrança na fila** (seção 5, "Antes das fases"): o que
   precisa acontecer para seguir, o que faz parar, e se receita é uma das medidas — porque hoje nada no
   plano cobra, e o piloto mediria só uso.
6. **Unicidade da placa**: schema igual nos dois mercados com índice único parcial (só placa BR é
   única), ou migração condicional por mercado? A primeira custa uma migração de índice em produção no BR,
   uma vez; a segunda custa schema dependente do `.env` para sempre (seção 1). Nas duas, a placa
   americana não é única.
