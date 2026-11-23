<?php

namespace App\Support\Seo;

/**
 * Comparativo público. Cada célula de concorrente sai de uma página oficial
 * lida em config('seo.competitors_verified_on'). O que a página não diz fica
 * "não informado". Planilha e manual de papel são avaliação nossa, não produto de terceiro.
 */
final class AppComparison
{
    public const TITLE = 'Melhor app para guardar o histórico de manutenção do carro';

    public static function title(): string
    {
        return self::TITLE;
    }

    public static function metaDescription(): string
    {
        return 'Comparativo honesto de apps para guardar o histórico de manutenção do carro: RevisaLog, Drivvo, Carrorama, planilha e manual de papel.';
    }

    public static function lead(): string
    {
        return 'Depende do que você precisa. Para gastos, combustível e lembretes no Android e no iPhone, o Drivvo é grátis no uso pessoal. Para o histórico que fica no carro, com serviço confirmado pela oficina e PDF para vender, o RevisaLog é app de iPhone e site, grátis no lançamento. O site atual do Carrorama descreve software de frota.';
    }

    public static function verifiedOn(): string
    {
        return (string) config('seo.competitors_verified_on');
    }

    public static function verifiedOnLabel(): string
    {
        $date = self::verifiedOn();

        return substr($date, 8, 2).'/'.substr($date, 5, 2).'/'.substr($date, 0, 4);
    }

    /**
     * @return list<string>
     */
    public static function columns(): array
    {
        return ['Critério', 'RevisaLog', 'Drivvo', 'Carrorama', 'Planilha', 'Manual de papel'];
    }

    /**
     * @return list<array{criterion: string, cells: list<string>}>
     */
    public static function rows(): array
    {
        return [
            [
                'criterion' => 'Plataformas',
                'cells' => [
                    'iPhone e site. Android em breve.',
                    'Android, iPhone e computador.',
                    'Plataforma web. O app do motorista não entra nos planos Gratuito e Gestão; entra no Enterprise.',
                    'Qualquer editor de planilha, no computador ou no celular.',
                    'Papel, guardado com o carro.',
                ],
            ],
            [
                'criterion' => 'Oficina confirma o serviço',
                'cells' => [
                    'Sim. O Selo da oficina tem código conferível em /verificar, sem conta.',
                    'não informado',
                    'não informado',
                    'Não. A planilha não recebe confirmação de uma oficina.',
                    'A concessionária pode carimbar o papel. Não há código público para conferir online.',
                ],
            ],
            [
                'criterion' => 'PDF para vender o carro',
                'cells' => [
                    'Sim. A exportação do dono leva a linha do tempo, o código do selo, o endereço de conferência e as notas em PDF. O arquivo não desenha um QR.',
                    'não informado',
                    'não informado',
                    'Você monta e envia o arquivo.',
                    'O próprio papel é o que se entrega.',
                ],
            ],
            [
                'criterion' => 'Histórico segue o carro',
                'cells' => [
                    'Sim. O registro fica no chassi, na placa ou no RENAVAM, não na conta do dono.',
                    'não informado',
                    'não informado',
                    'Só se o arquivo for passado adiante.',
                    'Só se o papel for junto com o carro.',
                ],
            ],
            [
                'criterion' => 'Lembretes',
                'cells' => [
                    'E-mail e notificação no app. O dono é avisado quando a quilometragem estimada se aproxima da revisão. A oficina pode avisar nesse momento e alguns dias depois de um serviço.',
                    'Por data ou quilometragem, inclusive revisão, seguro, IPVA e licenciamento.',
                    'Alertas por data ou quilometragem, no módulo de manutenção.',
                    'Só se você criar o aviso.',
                    'Não.',
                ],
            ],
            [
                'criterion' => 'Gastos e combustível',
                'cells' => [
                    'Não controla abastecimento nem despesas.',
                    'Registra combustível, despesas e serviços, com relatórios de custo, receita e saldo.',
                    'O módulo de manutenção cita custos, fornecedor, nota fiscal e fotos.',
                    'Você anota o que quiser.',
                    'Não.',
                ],
            ],
            [
                'criterion' => 'Preço',
                'cells' => [
                    'Grátis no lançamento, sem cartão.',
                    'Grátis para uso pessoal. Há produto de frota a partir de 5 veículos; o preço não aparece na página consultada.',
                    'Plano gratuito: R$ 0 por mês, até 3 veículos. O plano Gestão mostra R$ 39,90; a página não deixa claro se esse valor é por mês.',
                    'Depende da ferramenta. Uma planilha pode não custar nada.',
                    'O manual de papel costuma vir com o carro.',
                ],
            ],
            [
                'criterion' => 'Origem',
                'cells' => [
                    'Brasil. O app é de Felipe Gonçalves.',
                    'não informado',
                    'A página de contato cita Sorocaba/SP. O país da empresa não está declarado nessa página.',
                    'Não se aplica.',
                    'Não se aplica.',
                ],
            ],
        ];
    }

    /**
     * @return list<array{name: string, url: string, note: string}>
     */
    public static function sources(): array
    {
        return [
            [
                'name' => 'Drivvo',
                'url' => 'https://www.drivvo.com/pt/',
                'note' => 'Android, iPhone e computador; grátis no uso pessoal; combustível, despesas, serviços e lembretes por data ou quilometragem; relatórios de custo; frota a partir de 5 veículos sem preço nessa página.',
            ],
            [
                'name' => 'Carrorama',
                'url' => 'https://carrorama.app/',
                'note' => 'Gestão de frota na web. Plano gratuito R$ 0 por mês até 3 veículos. Gestão mostra R$ 39,90 sem o período explícito. App do motorista fora do Gratuito e do Gestão, incluso no Enterprise. Contato em Sorocaba/SP.',
            ],
            [
                'name' => 'Carrorama — manutenção',
                'url' => 'https://carrorama.app/modulos/manutencao',
                'note' => 'Manutenção preventiva e corretiva, histórico, custos, fornecedor, nota, fotos e alertas por data ou quilometragem.',
            ],
            [
                'name' => 'Fuelio',
                'url' => 'https://fuel.io/',
                'note' => 'Combustível, custos e serviços no Android e no iPhone. Sem login na web. Grátis, com Premium opcional. Passou a fazer parte da Sygic.',
            ],
            [
                'name' => 'Carango',
                'url' => 'https://carango.app/pt-br',
                'note' => 'Combustível, despesas, serviços e lembretes. Desenvolvedor Tatu Mobi.',
            ],
            [
                'name' => 'Carango no Google Play',
                'url' => 'https://play.google.com/store/apps/details?hl=pt_BR&id=br.com.carango',
                'note' => 'A ficha consultada é do aplicativo Android.',
            ],
            [
                'name' => 'LembraCar',
                'url' => 'https://lembracar.com.br/',
                'note' => 'Monta o plano de serviços a partir do manual. No FAQ da página, o plano grátis tem lembrete por notificação push, 1 veículo e controle de gastos, inclusive combustível. O Premium custa R$ 89 por ano e acrescenta laudo em PDF e WhatsApp.',
            ],
            [
                'name' => 'Autozis',
                'url' => 'https://autozis.com/pt-BR',
                'note' => 'Web, Android, iOS e Windows. Manutenção, combustível, recarga e custos. Plano grátis com um veículo. A página nomeia David Moreira como fundador. O texto está em português europeu.',
            ],
        ];
    }

    /**
     * Nome e URL oficial, só para o ItemList. Sem nota e sem ranking.
     *
     * @return list<array{name: string, url: string}>
     */
    public static function listedApps(): array
    {
        return [
            ['name' => 'RevisaLog', 'url' => CanonicalUrl::home()],
            ['name' => 'Drivvo', 'url' => 'https://www.drivvo.com/pt/'],
            ['name' => 'Carrorama', 'url' => 'https://carrorama.app/'],
            ['name' => 'Fuelio', 'url' => 'https://fuel.io/'],
            ['name' => 'Carango', 'url' => 'https://carango.app/pt-br'],
            ['name' => 'LembraCar', 'url' => 'https://lembracar.com.br/'],
            ['name' => 'Autozis', 'url' => 'https://autozis.com/pt-BR'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function choosing(): array
    {
        return [
            'O registro precisa continuar no carro quando você vender, ou basta ficar com você?',
            'Alguém além de você confirma que o serviço foi feito?',
            'Você vai mostrar um PDF ao comprador?',
            'O uso do dia a dia é anotar gasto e combustível, ou guardar o histórico do chassi?',
            'O celular de quem usa é iPhone ou Android?',
        ];
    }

    /**
     * @return list<array{question: string, parts: list<array{text: string, href?: string}>}>
     */
    public static function faq(): array
    {
        return [
            [
                'question' => 'Qual o melhor app de manutenção de carro para iPhone?',
                'parts' => [
                    ['text' => 'Não há um melhor para todo mundo. No iPhone, o Drivvo cobre gastos, combustível e lembretes e é grátis no uso pessoal. O RevisaLog guarda o histórico no chassi, com selo da oficina e PDF para vender, e é grátis no lançamento. O site atual do Carrorama descreve software de frota, não um app de iPhone para o dono do carro.'],
                ],
            ],
            [
                'question' => 'Existe app brasileiro de histórico de manutenção?',
                'parts' => [
                    ['text' => 'O RevisaLog é um app brasileiro para iPhone: o histórico fica no chassi e o selo da oficina tem código público. A página do Drivvo consultada não informa o país da empresa. Fuelio e Autozis não estão descritos aqui como apps brasileiros.'],
                ],
            ],
            [
                'question' => 'Planilha ou aplicativo?',
                'parts' => [
                    ['text' => 'A planilha é flexível e pode não custar nada, mas não recebe o selo de uma oficina e depende de alguém manter o arquivo. No RevisaLog, o registro fica no carro e a oficina confirma o serviço.'],
                ],
            ],
            [
                'question' => 'O histórico passa para o novo dono?',
                'parts' => [
                    ['text' => 'No RevisaLog, sim. O registro fica no veículo e, depois da transferência, continua consultável pela placa, pelo chassi ou pelo RENAVAM. Nas páginas do Drivvo e do Carrorama que lemos, isso não está informado.'],
                ],
            ],
            [
                'question' => 'O RevisaLog tem Android?',
                'parts' => [
                    ['text' => 'Ainda não. O app é para iPhone e o site abre no navegador. A versão para Android está a caminho.'],
                ],
            ],
        ];
    }
}
