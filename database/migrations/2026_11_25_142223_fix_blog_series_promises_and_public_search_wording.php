<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corrige no banco o texto que o BlogSeriesSeeder já gravou: o seeder só cria (firstOrCreate), então
 * trocar o Markdown em database/data/blog_series não alcança linha existente, publicada ou agendada.
 *
 * Cada troca vale só para o trecho exato do texto antigo: post sem o trecho (não semeado, ou editado
 * no admin) fica como está, e rodar de novo não faz nada. Usa o query builder para não mexer em
 * updated_at, que alimentaria "atualizado em" e o lastmod do sitemap sem mudança de conteúdo.
 */
return new class extends Migration
{
    /**
     * Cada item: slug, trecho antigo, trecho novo.
     *
     * @var list<array{0: string, 1: string, 2: string}>
     */
    private const REPLACEMENTS = [
        ['quilometragem-adulterada-sinais', '(o código DOT, explicado em um post futuro desta série)', '(o código DOT, gravado na lateral do pneu, termina em quatro dígitos: semana e ano de fabricação)'],
        ['quilometragem-adulterada-sinais', 'Faça a [consulta pública do RevisaLog](/buscar-veiculo)', 'Com uma conta gratuita, use a [busca do RevisaLog](/buscar-veiculo)'],
        ['como-fazer-o-cliente-voltar-a-oficina', '(o próximo post desta série trata disso)', '(art. 40)'],
        ['velas-de-ignicao-quando-trocar', 'O próximo post desta série trata das diferenças de manutenção entre rodar com etanol e com gasolina.', 'As diferenças de manutenção entre rodar com etanol e com gasolina variam de carro para carro: o manual do proprietário traz o que o fabricante recomenda para o seu.'],
        ['historico-de-carro-usado-como-consultar', '(o próximo post mostra onde ele fica)', '(ele fica gravado no carro e consta no documento)'],
        ['historico-de-carro-usado-como-consultar', 'Faça a [consulta pública do RevisaLog](/buscar-veiculo)', 'Com uma conta gratuita, use a [busca do RevisaLog](/buscar-veiculo)'],
        ['carro-de-leilao-sinistro-recuperado-vale-a-pena', 'Faça a [consulta pública do RevisaLog](/buscar-veiculo)', 'Com uma conta gratuita, use a [busca do RevisaLog](/buscar-veiculo)'],
        ['checklist-carro-usado', 'Faça a [consulta pública do RevisaLog](/buscar-veiculo)', 'Com uma conta gratuita, use a [busca do RevisaLog](/buscar-veiculo)'],
        ['chassi-onde-fica-como-ler', 'Use a [consulta pública do RevisaLog](/buscar-veiculo)', 'Com uma conta gratuita, use a [busca do RevisaLog](/buscar-veiculo)'],
    ];

    public function up(): void
    {
        foreach (self::REPLACEMENTS as [$slug, $old, $new]) {
            $post = DB::table('blog_posts')->where('slug', $slug)->first(['id', 'content']);

            if ($post === null || ! str_contains((string) $post->content, $old)) {
                continue;
            }

            DB::table('blog_posts')->where('id', $post->id)->update([
                'content' => str_replace($old, $new, (string) $post->content),
            ]);
        }
    }

    /**
     * A correção não se desfaz: voltar o texto antigo reintroduziria uma promessa que o blog não cumpre.
     */
    public function down(): void
    {
        //
    }
};
