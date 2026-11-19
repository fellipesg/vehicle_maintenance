{{--
    Corpo do laudo de vistoria cautelar, igual na emissão e na correção: os fatos da vistoria, os
    oito eixos de achado e, depois deles, o veredito com o resumo e o PDF.

    A ordem não é arrumação: o veredito é conclusão dos achados, e perguntá-lo antes convida a
    escolher "Aprovado" e só então olhar item por item. Com ele embaixo, a recusa de
    App\Services\Inspection\VehicleInspectionService ("Aprovado" sobre eixo reprovado) aparece logo
    abaixo do que a causou. Data e hodômetro ficam em cima porque são leitura, não juízo — e o eixo
    "Hodômetro" dos achados fala justamente da leitura registrada ali.

    Os eixos vêm de App\Enums\InspectionFinding — sempre todos, na ordem do enum, porque "não olhei"
    e "olhei e está bem" são respostas diferentes e o padrão é "Não avaliado"
    (App\Support\Inspection\InspectionFindings). A tela não inventa eixo nem rótulo.

    Variáveis: $inspection (null na emissão).
--}}
@php
    use App\Enums\InspectionFinding;
    use App\Enums\InspectionFindingStatus;
    use App\Enums\InspectionVerdict;
    use App\Models\VehicleInspection;
    use App\Support\Inspection\InspectionFindings;

    $inspection = $inspection ?? null;
    $findings = InspectionFindings::normalize($inspection?->findings);
    $reportName = $inspection?->report_original_name;
@endphp

<x-ui.form-section id="secao-vistoria" :number="2" title="Vistoria"
    description="Quando o veículo foi examinado, o que o hodômetro marcava e até quando o laudo vale.">
    <div class="grid items-start gap-4 sm:grid-cols-2">
        <x-ui.field name="inspected_at" label="Data da vistoria" required>
            <x-ui.input type="date" :value="$inspection?->inspected_at?->toDateString()" required max="{{ now()->toDateString() }}" />
        </x-ui.field>

        <x-ui.field name="valid_until" label="Validade do laudo" optional
                    hint="Em branco, o laudo não vence. Laudo vencido continua no histórico, marcado.">
            <x-ui.input type="date" :value="$inspection?->valid_until?->toDateString()" />
        </x-ui.field>

        <x-ui.field name="odometer_km" label="Hodômetro lido" optional
                    hint="A leitura fica no laudo e não altera a quilometragem do veículo.">
            <x-ui.input type="number" :value="$inspection?->odometer_km" min="0" max="9999999" inputmode="numeric" class="tabular-nums" />
        </x-ui.field>
    </div>
</x-ui.form-section>

<x-ui.form-section id="secao-achados" :number="3" title="Achados"
    description="Um resultado por item examinado. O item que a vistoria não olhou fica em “Não avaliado”: laudo que afirma mais do que examinou é pior que laudo incompleto.">
    <div class="space-y-5">
        @foreach(InspectionFinding::cases() as $axis)
            <div class="grid items-start gap-3 sm:grid-cols-[minmax(0,20rem)_minmax(0,1fr)]" data-finding="{{ $axis->value }}">
                <x-ui.field :name="'findings['.$axis->value.'][status]'" :label="$axis->label()" :hint="$axis->meaning()">
                    <x-ui.select :options="InspectionFindingStatus::options()" :value="$findings[$axis->value]['status']" />
                </x-ui.field>
                <x-ui.field :name="'findings['.$axis->value.'][note]'" label="Observação" optional
                            hint="Não inclua nome, CPF, telefone ou placa do cliente.">
                    <x-ui.input :value="$findings[$axis->value]['note']" :maxlength="InspectionFindings::MAX_NOTE_LENGTH" autocomplete="off" />
                </x-ui.field>
            </div>
        @endforeach
    </div>
</x-ui.form-section>

<x-ui.form-section id="secao-veredito" :number="4" title="Veredito e laudo"
    description="O veredito conclui os achados acima e é o que o comprador lê primeiro. Resumo e observações só aparecem para o proprietário, para quem ele autorizar e para a sua oficina.">
    <div class="grid items-start gap-4 sm:grid-cols-2">
        <x-ui.field name="verdict" label="Resultado" required
                    hint="Com algum eixo reprovado acima, só cabe “Aprovado com restrições” ou “Reprovado”.">
            <x-ui.select :options="InspectionVerdict::options()" :value="$inspection?->verdict?->value" placeholder="Escolha o resultado" required />
        </x-ui.field>
    </div>

    <x-ui.field name="summary" label="Resumo da vistoria" optional
                hint="Não inclua nome, CPF, telefone ou placa do cliente.">
        <x-ui.textarea :value="$inspection?->summary" rows="5" counter :maxlength="VehicleInspection::MAX_SUMMARY_LENGTH" />
    </x-ui.field>

    @if(filled($reportName))
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-control border border-border bg-surface-muted/60 p-3" data-current-report>
            <p class="min-w-0 text-sm">
                <span class="text-muted-foreground">Laudo anexado:</span>
                <x-ui.link :href="route('inspections.report', $inspection)" icon="document-text" class="break-all">{{ $reportName }}</x-ui.link>
            </p>
            <x-ui.checkbox name="remove_report" value="1" label="Remover este PDF ao salvar" />
        </div>
    @endif

    <x-ui.field name="report" :label="filled($reportName) ? 'Substituir o PDF do laudo' : 'Laudo em PDF'" optional
                :hint="filled($reportName) ? 'Ao enviar outro arquivo, o anterior é apagado.' : 'O arquivo que você entrega ao cliente. Só PDF.'">
        <x-ui.file-input accept="application/pdf" :max-mb="VehicleInspection::MAX_REPORT_KILOBYTES / 1024" />
    </x-ui.field>
</x-ui.form-section>
