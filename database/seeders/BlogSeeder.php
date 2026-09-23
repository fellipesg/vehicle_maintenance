<?php

namespace Database\Seeders;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Database\Seeder;

class BlogSeeder extends Seeder
{
    /**
     * Categorias e artigos iniciais do blog. Idempotente: roda de novo sem duplicar.
     */
    public function run(): void
    {
        $categories = [];

        foreach ($this->categories() as $category) {
            $categories[$category['slug']] = BlogCategory::updateOrCreate(
                ['slug' => $category['slug']],
                $category + ['is_active' => true],
            );
        }

        foreach ($this->posts() as $index => $post) {
            BlogPost::updateOrCreate(
                ['slug' => $post['slug']],
                [
                    'blog_category_id' => $categories[$post['category']]->id,
                    'cover_art' => $post['art'],
                    'title' => $post['title'],
                    'excerpt' => $post['excerpt'],
                    'content' => $post['content'],
                    'meta_description' => $post['excerpt'],
                    'status' => BlogPost::STATUS_PUBLISHED,
                    'published_at' => now()->subDays(($index + 1) * 7),
                ],
            );
        }
    }

    /**
     * @return list<array{name: string, slug: string, description: string}>
     */
    private function categories(): array
    {
        return [
            [
                'name' => 'Plataforma',
                'slug' => 'plataforma',
                'description' => 'Como o RevisaLog funciona, o que cada perfil pode fazer e como o histórico é registrado.',
            ],
            [
                'name' => 'Manutenção',
                'slug' => 'manutencao',
                'description' => 'Revisões, trocas e cuidados que mantêm o carro rodando com segurança.',
            ],
            [
                'name' => 'Documentação',
                'slug' => 'documentacao',
                'description' => 'CRLV, licenciamento, IPVA e a papelada que acompanha o veículo.',
            ],
            [
                'name' => 'Compra e venda',
                'slug' => 'compra-e-venda',
                'description' => 'O que olhar antes de comprar e como um histórico limpo ajuda na hora de vender.',
            ],
        ];
    }

    /**
     * @return list<array{slug: string, art: string, category: string, title: string, excerpt: string, content: string}>
     */
    private function posts(): array
    {
        return [
            [
                'slug' => 'como-o-revisalog-guarda-o-historico-do-seu-carro',
                'art' => 'historico',
                'category' => 'plataforma',
                'title' => 'Como o RevisaLog guarda o histórico do seu carro',
                'excerpt' => 'O registro de manutenções fica vinculado ao veículo pelo chassi — não à conta de quem cadastrou. Entenda o que isso muda na prática.',
                'content' => <<<'MD'
A maioria dos aplicativos de manutenção guarda os registros dentro da sua conta. Quando o carro é vendido, o histórico fica para trás: o comprador recebe o veículo, mas não recebe a memória do que foi feito nele.

No RevisaLog o vínculo é outro. Cada manutenção é registrada **no veículo**, identificado pelo chassi (VIN). A conta é apenas a chave de acesso a esse registro.

## Por que o chassi

A placa muda. O proprietário muda. O chassi acompanha o carro da fábrica ao desmanche. É o único identificador que não se altera ao longo da vida do veículo, e por isso é ele que sustenta o histórico.

Quando a placa é trocada, o RevisaLog guarda a placa anterior. A busca continua funcionando pelos três caminhos: chassi, RENAVAM ou placa — atual ou antiga.

## O que fica registrado

Cada manutenção guarda:

- data do serviço e quilometragem do odômetro;
- itens trocados ou revisados, com valores quando informados;
- a oficina responsável, quando o registro parte dela;
- fotos e nota fiscal, se anexadas;
- garantia dos itens, quando a oficina aplica um modelo de garantia.

## Quem pode registrar

- **O proprietário**, pelo app ou pelo portal web, para registros do dia a dia.
- **A oficina**, pelo portal da oficina, com o selo de verificação.

Os dois tipos aparecem na mesma linha do tempo, com a origem sempre visível. Ninguém precisa escolher entre um e outro.

## Na hora de vender

O comprador consulta o histórico pelo chassi, placa ou RENAVAM antes de fechar negócio. Você não precisa procurar notas fiscais antigas nem confiar na memória: a linha do tempo já está lá, com as datas e as quilometragens que foram registradas.
MD,
            ],
            [
                'slug' => 'selo-da-oficina-manutencao-verificada',
                'art' => 'selo',
                'category' => 'plataforma',
                'title' => 'Selo da oficina: o que muda quando a manutenção é verificada',
                'excerpt' => 'Registros feitos pela oficina recebem um selo de verificação e um código público de consulta. Veja a diferença entre um registro do proprietário e um registro verificado.',
                'content' => <<<'MD'
Nem todo registro tem o mesmo peso. Uma troca de óleo anotada pelo proprietário vale como memória do que foi feito. A mesma troca registrada pela oficina que executou o serviço vale como comprovação.

O RevisaLog separa os dois casos de forma explícita, sem descartar nenhum deles.

## Registro do proprietário

É o registro do dia a dia: você abre o app, informa data, quilometragem e o que foi feito. Ele entra na linha do tempo com a origem identificada como registro do proprietário.

Serve para não perder o controle dos intervalos — e para reconstruir o histórico de serviços antigos que você fez antes de conhecer a plataforma.

## Registro verificado pela oficina

Quando a oficina cadastrada registra o serviço pelo portal dela, o registro nasce **verificado**. Ele recebe:

- o selo de verificação na linha do tempo;
- a identificação da oficina que executou o serviço;
- um código público de verificação, no formato `RVL-XXXX-XX`.

Esse código abre uma página pública de conferência. Qualquer pessoa com o código — um comprador, por exemplo — confirma que aquele serviço foi mesmo registrado por aquela oficina, naquela data e naquela quilometragem.

## O que a verificação protege

Um registro verificado não pode ser editado nem apagado por quem não é a oficina responsável. Isso evita o cenário mais óbvio de fraude: alterar quilometragem ou apagar um serviço problemático antes de anunciar o carro.

## Como pedir o registro verificado

Pergunte na oficina se ela usa o RevisaLog. Se usar, peça para registrar o serviço pelo portal — leva o mesmo tempo que preencher a ordem de serviço. Se ainda não usar, você registra pelo app e guarda a nota fiscal como anexo.
MD,
            ],
            [
                'slug' => 'troca-de-oleo-quando-fazer-por-km-e-por-tempo',
                'art' => 'troca-de-oleo',
                'category' => 'manutencao',
                'title' => 'Troca de óleo: quando fazer por quilometragem e por tempo',
                'excerpt' => 'O intervalo de troca tem dois limites — o de quilômetros e o de meses — e vale o que vencer primeiro. Entenda como o manual define isso.',
                'content' => <<<'MD'
O intervalo de troca de óleo aparece no manual do proprietário com dois números: uma quilometragem e um prazo. **Vale o que chegar primeiro.** Um carro que roda pouco chega ao limite de tempo antes de chegar ao limite de quilômetros — e ainda assim precisa da troca.

## Por que o tempo conta

O óleo se degrada mesmo parado. A combustão contamina o lubrificante com resíduos e água; o motor que roda pouco e em trajetos curtos nem chega à temperatura ideal para evaporar essa umidade. O resultado é um óleo que perde propriedades antes de completar a quilometragem prevista.

## Onde encontrar o intervalo correto

O único número que vale para o seu carro é o do manual do proprietário, porque ele depende do motor, da especificação do óleo e do tipo de uso. Antes de seguir a recomendação genérica da loja de peças, confira:

1. o intervalo em quilômetros e em meses;
2. a viscosidade especificada (por exemplo, 5W30);
3. a norma exigida pela montadora (API, ACEA ou a norma própria da marca);
4. se há um intervalo reduzido para **uso severo**.

## O que conta como uso severo

O manual quase sempre traz uma tabela separada para condições severas, com intervalo menor. Costumam entrar nessa lista:

- trajetos curtos, com o motor raramente atingindo a temperatura de trabalho;
- trânsito parado por longos períodos;
- estradas de terra ou muita poeira;
- reboque, carga pesada ou uso em subidas constantes.

Se a sua rotina é essa, o intervalo curto é a referência, não o longo.

## O filtro acompanha

O filtro de óleo é trocado junto. Reaproveitar o filtro antigo devolve sujeira ao óleo novo logo nos primeiros quilômetros.

## Registre a troca

Anote a data **e** a quilometragem de cada troca. É esse par que permite calcular quando a próxima vence — e é o que um comprador olha primeiro para saber se o motor foi cuidado.
MD,
            ],
            [
                'slug' => 'revisao-programada-o-que-e-checado',
                'art' => 'revisao',
                'category' => 'manutencao',
                'title' => 'Revisão programada: o que é checado e por que os intervalos existem',
                'excerpt' => 'A revisão de cada marco de quilometragem não é uma lista aleatória: cada item tem uma vida útil estimada. Veja o que costuma entrar na conta.',
                'content' => <<<'MD'
A revisão programada é um plano de substituição preventiva. Cada item entra na lista em um marco específico porque a montadora estima que ele chega ao fim da vida útil por ali — antes de falhar em uso.

Os marcos e os itens variam por modelo. O que segue é o desenho geral; o plano do seu carro está no manual.

## Revisões curtas

Nos intervalos mais frequentes, o foco é o que se consome rápido:

- óleo do motor e filtro de óleo;
- filtro de ar do motor e filtro do ar-condicionado;
- nível e estado dos fluidos de freio, arrefecimento e direção;
- pastilhas e discos de freio, medidos, não só olhados;
- pneus: pressão, profundidade dos sulcos e desgaste irregular;
- luzes, palhetas do limpador e bateria.

## Revisões intermediárias

Em marcos maiores entram itens de vida útil mais longa:

- velas de ignição;
- filtro de combustível;
- fluido de freio, que absorve umidade com o tempo e é trocado por prazo;
- alinhamento e balanceamento;
- verificação de suspensão, coifas, buchas e amortecedores.

## Revisões de marco alto

São as caras, e as que mais aparecem em carro de segunda mão sem histórico:

- correia dentada e tensor, quando o motor usa correia — falha nesse item pode destruir o motor;
- fluido de arrefecimento;
- fluido da transmissão automática, conforme o plano da montadora;
- correia acessória.

## Por que registrar cada revisão

Sem registro, ninguém sabe se a correia dentada foi trocada aos 60 mil ou se nunca foi. Na dúvida, o comprador assume que não foi — e o custo do serviço entra no desconto que ele vai pedir. Uma linha do tempo com data, quilometragem e oficina resolve a dúvida sem discussão.
MD,
            ],
            [
                'slug' => 'crlv-licenciamento-e-ipva-o-que-e-cada-um',
                'art' => 'documento',
                'category' => 'documentacao',
                'title' => 'CRLV, licenciamento e IPVA: o que é cada um',
                'excerpt' => 'Três nomes que costumam ser confundidos e não são a mesma coisa. Entenda a função de cada documento e como o CRLV preenche o cadastro do veículo.',
                'content' => <<<'MD'
CRLV, licenciamento e IPVA aparecem juntos todo ano e são tratados como sinônimos na conversa do dia a dia. São coisas diferentes, e a diferença importa quando algo dá errado.

## IPVA

É o imposto sobre a propriedade do veículo, estadual, cobrado anualmente com base no valor venal. O calendário de vencimento é definido por cada estado e costuma variar conforme o final da placa.

## Licenciamento

É o procedimento anual que autoriza o veículo a circular. Depende de estar em dia com o IPVA, com as multas e, quando exigida, com a inspeção. O valor da taxa e as regras são definidos pelo Detran de cada estado.

## CRLV

É o documento que **comprova** o licenciamento do exercício. Hoje ele é emitido em formato digital (CRLV-e), com QR Code de verificação, e pode ser apresentado pelo celular. É esse documento que reúne, numa única página, os dados de identificação do veículo.

## O que o CRLV traz

O documento concentra exatamente o que identifica o carro:

- placa e RENAVAM;
- chassi (VIN);
- marca, modelo e ano de fabricação/modelo;
- cor predominante;
- categoria, combustível e potência;
- exercício licenciado.

## Usando o CRLV no cadastro

No RevisaLog, o cadastro do veículo aceita a importação do CRLV: os dados de identificação são lidos do documento e preenchem o formulário, o que evita erro de digitação — principalmente no chassi, que tem 17 caracteres e é a chave do histórico.

Confira os campos preenchidos antes de salvar. Chassi errado significa histórico registrado no carro errado.

## Guarde as datas

O vencimento do licenciamento e o do IPVA não são datas de manutenção, mas fazem parte do mesmo calendário anual do veículo. Anotar os dois junto com as revisões evita a surpresa de circular com documento vencido.
MD,
            ],
            [
                'slug' => 'vender-o-carro-com-historico-de-manutencao',
                'art' => 'venda',
                'category' => 'compra-e-venda',
                'title' => 'Vender o carro com histórico: o que o comprador quer ver',
                'excerpt' => 'Quem compra usado está calculando risco. Um histórico com datas, quilometragens e oficinas identificadas tira parte desse risco da conta.',
                'content' => <<<'MD'
Toda negociação de carro usado é uma conversa sobre risco. O comprador não sabe o que foi feito no veículo, então assume o pior cenário e desconta esse valor da oferta. Histórico é a forma mais direta de reduzir essa margem de dúvida.

## O que o comprador tenta descobrir

- A quilometragem é real ou o odômetro foi adulterado?
- As revisões caras já foram feitas — ou vão vencer logo depois da compra?
- O carro teve manutenção regular ou só conserto de emergência?
- Existe nota fiscal e oficina identificável por trás de cada serviço?

## Como o histórico responde a isso

Uma linha do tempo com data e quilometragem em cada registro mostra a progressão do odômetro ao longo dos anos. Saltos incoerentes ficam visíveis; a evolução regular também.

Quando o serviço foi registrado pela oficina, o selo de verificação e o código público confirmam a origem. O comprador consulta o código e vê a confirmação, sem depender da sua palavra.

## O que fazer antes de anunciar

1. **Complete os registros antigos.** Se você tem notas fiscais de serviços anteriores, registre-os com a data e a quilometragem que constam na nota.
2. **Resolva o que está vencido.** Um item de revisão em atraso vira argumento de desconto.
3. **Peça registro verificado.** Nos últimos serviços antes da venda, peça para a oficina registrar pelo portal dela.
4. **Anexe as notas.** Fotos e documentos sustentam o registro.
5. **Gere o PDF do histórico.** O relatório consolidado é o que você envia para o interessado antes mesmo da visita.

## Do outro lado da negociação

Se você está comprando, faça o caminho inverso: peça o chassi no anúncio e consulte o histórico antes de agendar a visita. Vale para confirmar o que existe — e para saber o que perguntar sobre o que não existe.
MD,
            ],
        ];
    }
}
