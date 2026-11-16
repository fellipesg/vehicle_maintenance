# Plano: série de posts do blog para atrair visitas

Status: **plano** — nenhum post escrito ainda.  
Data: 2026-11-15  
Onde publicar: Admin → Blog → Novo artigo, com **Agendar** (horário de Brasília; o post entra no ar
sozinho na hora marcada, sem comando nem cron — ver `.ai/rules/blog.md`).

## Objetivo

Trazer visitas de busca orgânica que (1) viram cadastro de dono ou de oficina e (2) sustentam o
AdSense no blog. Hoje existem 6 posts (`database/seeders/BlogSeeder.php`): como o RevisaLog guarda o
histórico, selo da oficina, troca de óleo, revisão programada, CRLV/licenciamento/IPVA e vender com
histórico. A série abaixo não repete esses assuntos e linka para eles.

## Critérios de escolha

- **Intenção de busca real**: perguntas que o dono digita no Google ("quando trocar…", "como saber
  se…", "vale a pena…"), não assuntos institucionais.
- **Ligação com o produto**: todo post termina num próximo passo dentro do RevisaLog (registrar a
  manutenção, consultar um veículo, convidar a oficina).
- **Nicho que paga melhor no AdSense**: compra de usado, seguro, pneus, freios e bateria atraem
  anunciantes de valor mais alto do que conteúdo genérico.
- **Nada inventado**: intervalos, preços e regras citam a fonte (manual do fabricante, Detran,
  Contran, Procon/CDC). Onde houver dúvida jurídica, o post orienta e não afirma.

## Ritmo e horário

- **2 posts por semana**, terça e quinta, às **08:00** (horário de Brasília).
- 22 posts em 12 semanas, de **17/11/2026** a **04/02/2027**. As quintas de 24/12 e 31/12 ficam sem post (véspera de feriado, pouca leitura).
- Escrever com pelo menos uma semana de folga e deixar tudo **agendado** no admin.

## Séries

### A. Comprar usado sem cair em armadilha (categoria: Compra e venda)

O assunto de maior volume e o que mais converte em consulta e cadastro.

| # | Data | Título | Palavra-chave | Capa | Pontos do texto |
| --- | --- | --- | --- | --- | --- |
| A1 | 17/11 ter | Como consultar o histórico de um carro usado antes de comprar | histórico carro usado | `historico` | O que dá para consultar (débitos, restrições, recall, manutenção); onde cada um fica; como pedir o histórico ao vendedor; CTA: consulta pública do RevisaLog |
| A2 | 19/11 qui | Chassi: onde fica, como ler e o que ele revela | onde fica o chassi | `documento` | Locais de gravação (vidros, motor, assoalho, etiquetas); os 17 caracteres; ano-modelo pelo 10º dígito; remarcação e o que fazer se não bater com o documento |
| A3 | 24/11 ter | Quilometragem adulterada: 7 sinais que o carro foi "voltado" | km adulterado | `carro` | Desgaste de volante, pedais e bancos × km; notas e etiquetas de troca de óleo; histórico de revisões; por que o km de cada manutenção registrada protege o comprador |
| A4 | 26/11 qui | Checklist para ver um carro usado pessoalmente | checklist carro usado | `revisao` | Lista imprimível: lataria, motor frio, fumaça, suspensão, test-drive, documentos; levar mecânico de confiança |
| A5 | 01/12 ter | Carro de leilão, sinistro e recuperado: vale a pena? | carro de leilão vale a pena | `venda` | Diferença entre os tipos; impacto no seguro e na revenda; como identificar; quando pode valer |
| A6 | 03/12 qui | Revisões na concessionária ou em oficina independente: o que perde a garantia | garantia revisão fora da concessionária | `selo` | O que o manual exige; o que o CDC e o Procon dizem (citar fonte, sem afirmar além dela); guardar notas e registros; CTA: selo da oficina |

### B. Manutenção por componente (categoria: Manutenção)

Buscas recorrentes, com picos sazonais. Cada post tem um quadro "quando trocar" com a fonte.

| # | Data | Título | Palavra-chave | Capa | Pontos do texto |
| --- | --- | --- | --- | --- | --- |
| B1 | 08/12 ter | Correia dentada: quando trocar e o que acontece se romper | quando trocar correia dentada | `troca-de-oleo` | Intervalo por km e por tempo no manual; motores interferentes; correia banhada a óleo; trocar o kit junto |
| B2 | 10/12 qui | Pastilha e disco de freio: sinais de desgaste e intervalos | quando trocar pastilha de freio | `revisao` | Ruído, vibração e luz no painel; espessura mínima; fluido de freio a cada 2 anos (conferir o manual) |
| B3 | 15/12 ter | Pneus: quando trocar, como ler o código e o que é o TWI | quando trocar pneu | `carro` | TWI e 1,6 mm (Contran); data DOT; rodízio, alinhamento e balanceamento; calibragem |
| B4 | 17/12 qui | Viagem de fim de ano: o que revisar antes de pegar a estrada | revisão antes de viajar | `revisao` | Checklist sazonal: pneus e estepe, freios, fluidos, luzes, palhetas, bateria, documentos em dia |
| B5 | 22/12 ter | Bateria do carro: quanto dura e como saber se está no fim | bateria do carro quanto tempo dura | `carro` | Vida útil típica; partida lenta; teste de tensão; carro parado; descarte correto |
| B6 | 29/12 ter | Ar-condicionado do carro: filtro de cabine, gás e higienização | filtro de cabine quando trocar | `revisao` | Intervalo do filtro; cheiro e alergia; quando é gás e quando é compressor; verão |
| B7 | 05/01 ter | Fluido de arrefecimento: o que é e por que não completar com água | fluido de arrefecimento | `troca-de-oleo` | Função; mistura correta; superaquecimento; intervalo de troca |
| B8 | 07/01 qui | Amortecedores: como saber se estão gastos | amortecedor gasto sinais | `carro` | Teste de oscilação, vazamento, desgaste irregular do pneu; trocar em par |
| B9 | 12/01 ter | Velas e bobinas: falhas, consumo e intervalos | quando trocar vela de ignição | `troca-de-oleo` | Sintomas; diferença entre níquel, platina e irídio; motor flex |
| B10 | 14/01 qui | Carro flex: álcool ou gasolina faz diferença na manutenção? | carro flex manutenção | `troca-de-oleo` | Óleo, partida a frio, bicos, tanquinho; o que o manual recomenda |

### C. Documentos e Detran (categoria: Documentação)

Volume alto no começo do ano (IPVA e licenciamento).

| # | Data | Título | Palavra-chave | Capa | Pontos do texto |
| --- | --- | --- | --- | --- | --- |
| C1 | 19/01 ter | IPVA 2027: calendário, desconto e o que acontece se atrasar | IPVA 2027 | `documento` | Como achar o calendário do seu estado (link para cada Secretaria da Fazenda); cota única × parcelado; multa e juros. Linkar o post de CRLV |
| C2 | 21/01 qui | Transferência de veículo: passo a passo e prazo de 30 dias | transferência de veículo prazo | `venda` | ATPV-e; comunicação de venda; vistoria; o que o vendedor e o comprador fazem; o histórico continua no chassi |
| C3 | 26/01 ter | Recall: como saber se o seu carro tem e por que nunca ignorar | consultar recall | `documento` | Onde consultar (montadora, Senatran); recall pendente na venda; registrar o atendimento no histórico |
| C4 | 28/01 qui | Multas e pontos na CNH: como consultar e recorrer | consultar multas | `documento` | Onde consultar; prazos de defesa e recurso; indicar condutor |

### D. Para oficinas (categoria: Plataforma)

Menos volume, mas é o público que paga o plano. Linkar `/para-oficinas`.

| # | Data | Título | Palavra-chave | Capa | Pontos do texto |
| --- | --- | --- | --- | --- | --- |
| D1 | 02/02 ter | Como fazer o cliente voltar para a próxima revisão | fidelizar cliente oficina mecânica | `selo` | Lembrete por km e por tempo; histórico na mão do cliente; mensagens automáticas da oficina no RevisaLog |
| D2 | 04/02 qui | Ordem de serviço na oficina: o que precisa constar | ordem de serviço oficina mecânica | `historico` | Orçamento prévio (CDC art. 40), peças, mão de obra, garantia do serviço (CDC art. 26); OS com selo; nota fiscal anexada |

## Formato de cada post

- **Título** com a palavra-chave no começo; **meta title** de até 60 caracteres e **meta description**
  de até 155, com a palavra-chave e um benefício.
- **Resumo** (excerpt) de 1 a 2 frases: aparece no card e no feed.
- **900 a 1.500 palavras** em Markdown, com H2 a cada 200–300 palavras; um quadro ou lista "na
  prática" que dê para salvar ou imprimir.
- **Fontes** no fim (manual, Contran, Detran, Procon), com link.
- **2 links internos**: um post anterior da série e um post já publicado.
- **CTA no fim**, um só: registrar a manutenção, consultar um veículo ou cadastrar a oficina.
- **Capa**: foto 1200 × 630 ou a cena SVG indicada na tabela (`cover_art`).
- Os blocos de anúncio entram sozinhos pelo layout do blog (PR do AdSense); não colocar anúncio no
  texto.

## Depois de publicar

- **Search Console**: enviar o `/sitemap.xml` uma vez e acompanhar impressões e cliques por post.
- **Revisão a cada 3 meses**: posts com muitas impressões e poucos cliques ganham título e meta
  description novos; posts sem impressões depois de 3 meses mudam de palavra-chave ou saem.
- **Próxima série** (depois de fev/2027): "Revisão do [modelo]" para os 10 carros mais vendidos (Onix,
  HB20, Strada, Polo, T-Cross…), com o plano de revisão de cada fabricante. Volume de busca alto, mas
  exige conferir cada manual.
