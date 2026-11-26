<?php

namespace App\Services\Vehicle;

use App\Models\Maintenance;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class VehicleMileageService
{
    public function __construct(private readonly VehicleOdometerLog $odometerLog) {}

    /**
     * Folga fixa do teto de plausibilidade, em km (ver plausibilityCeiling()).
     */
    private const PLAUSIBILITY_BASE_KILOMETERS = 50_000;

    /**
     * Folga do teto de plausibilidade por ano de vida do veículo, em km (ver plausibilityCeiling()).
     */
    private const PLAUSIBILITY_KILOMETERS_PER_YEAR = 100_000;

    /**
     * Garante que a quilometragem informada é coerente com a linha do tempo do veículo.
     *
     * O hodômetro do cadastro só serve de piso para manutenções feitas a partir da data
     * de cadastro. Manutenções anteriores (histórico) só precisam respeitar os registros
     * vizinhos: no mínimo o km do registro anterior e no máximo o km do registro seguinte.
     *
     * Acima disso vale o teto de plausibilidade derivado do ano do veículo (plausibilityCeiling()),
     * que é o único limite quando não existe registro posterior — o caso comum de uma oficina
     * registrando pela primeira vez.
     *
     * @throws ValidationException
     */
    public function assertMaintenanceKilometers(
        Vehicle $vehicle,
        int $kilometers,
        CarbonInterface|string|null $maintenanceDate = null,
        ?Maintenance $except = null,
    ): void {
        if ($kilometers < 0) {
            throw ValidationException::withMessages([
                'kilometers' => 'A quilometragem deve ser zero ou maior.',
            ]);
        }

        $date = $this->normalizeDate($maintenanceDate);

        $floor = $this->minimumAllowedKilometers($vehicle, $date, $except);

        if ($kilometers < $floor) {
            throw ValidationException::withMessages([
                'kilometers' => "A quilometragem deve ser no mínimo {$floor} km (hodômetro ou manutenção já registrada até essa data).",
            ]);
        }

        $ceiling = $this->maximumAllowedKilometers($vehicle, $date, $except);

        if ($ceiling !== null && $kilometers > $ceiling) {
            throw ValidationException::withMessages([
                'kilometers' => "A quilometragem deve ser no máximo {$ceiling} km, pois já existe registro posterior a essa data com essa quilometragem.",
            ]);
        }

        $plausible = $this->plausibilityCeiling($vehicle, $floor);

        if ($plausible !== null && $kilometers > $plausible) {
            throw ValidationException::withMessages([
                'kilometers' => "A quilometragem deve ser no máximo {$plausible} km: é o teto plausível para um veículo {$vehicle->year}. Confira se não sobrou um dígito.",
            ]);
        }
    }

    /**
     * Hodômetro lido numa vistoria cautelar. **Não** é a mesma regra da OS, e a diferença é o ponto
     * principal: `assertMaintenanceKilometers` impõe monotonicidade (piso em
     * `minimumAllowedKilometers`), e a vistoria é o único ator com licença para quebrá-la. Leitura
     * abaixo do já registrado é exatamente o achado que a cautelar existe para encontrar —
     * adulteração de hodômetro —, e recusá-la proibiria o laudo de relatar o que viu.
     *
     * Sobra o que pega erro de digitação sem calar o vistoriador: negativo e o teto de
     * plausibilidade derivado do ano do veículo.
     *
     * @throws ValidationException
     */
    public function assertInspectionKilometers(Vehicle $vehicle, int $kilometers): void
    {
        if ($kilometers < 0) {
            throw ValidationException::withMessages([
                'odometer_km' => 'A quilometragem deve ser zero ou maior.',
            ]);
        }

        $plausible = $this->plausibilityCeiling($vehicle, 0);

        if ($plausible !== null && $kilometers > $plausible) {
            throw ValidationException::withMessages([
                'odometer_km' => "A quilometragem deve ser no máximo {$plausible} km: é o teto plausível para um veículo {$vehicle->year}. Confira se não sobrou um dígito.",
            ]);
        }
    }

    /**
     * Teto de plausibilidade do hodômetro informado no **cadastro e na edição do veículo** (campo
     * `current_kilometers`) — as portas que `max:9999999` era o único limite a cobrir.
     *
     * É o mesmo teto da OS (plausibilityCeilingFor()), e o motivo de ele valer aqui é que o número
     * do cadastro não fica no cadastro: vira `odometer_at_registration`, o piso de toda OS seguinte
     * e um dos termos do próprio teto. Deixar essa porta aberta neutralizava o teto nas quatro
     * superfícies de OS daquele chassi.
     *
     * O ano vem de quem está gravando (o formulário pode estar mudando o ano do veículo), e
     * $vehicle é o veículo quando ele já existe — na edição e na chegada do proprietário a um
     * veículo que a oficina criou pelo chassi.
     *
     * @throws ValidationException
     */
    public function assertOdometerKilometers(
        int $kilometers,
        ?int $year,
        ?Vehicle $vehicle = null,
        string $attribute = 'current_kilometers',
    ): void {
        if ($kilometers < 0) {
            throw ValidationException::withMessages([
                $attribute => 'A quilometragem deve ser zero ou maior.',
            ]);
        }

        $ceiling = $this->plausibilityCeilingFor($year, $this->alreadyRunKilometers($vehicle));

        if ($ceiling !== null && $kilometers > $ceiling) {
            throw ValidationException::withMessages([
                $attribute => "A quilometragem deve ser no máximo {$ceiling} km: é o teto plausível para um veículo {$year}. Confira se não sobrou um dígito.",
            ]);
        }
    }

    /**
     * A OS que conta para o hodômetro com a maior quilometragem **acima** desta leitura, ou null
     * quando a leitura é coerente com o histórico.
     *
     * É o detector de hodômetro rodado para trás: quem lê 40.000 num carro com OS de 90.000 está
     * diante de um indício, não de um erro. Quem usa isto é o VehicleInspectionService, para recusar
     * um "sem apontamento" no eixo do hodômetro.
     *
     * A **tela** da vistoria não mostra o registro contradito, de propósito: o número e a data de
     * uma OS de outra oficina são exatamente o que o mascaramento esconde de quem não tem vínculo
     * com o veículo (.ai/rules/public-lookup.md). Ele só aparece na mensagem da recusa, que custa
     * uma tentativa de gravação com `throttle:uploads` — a mesma exceção consciente que o piso do
     * hodômetro já tem na OS.
     */
    public function countedMaintenanceAbove(Vehicle $vehicle, int $kilometers): ?Maintenance
    {
        return $vehicle->maintenances()
            ->countsTowardOdometer()
            ->whereNotNull('kilometers')
            ->where('kilometers', '>', $kilometers)
            ->orderByDesc('kilometers')
            ->orderByDesc('maintenance_date')
            ->first();
    }

    /**
     * Hodômetro de cadastro do veículo. Grava `current_kilometers` **e**
     * `odometer_at_registration`, que são o piso de toda OS seguinte e entram no teto de
     * plausibilidade — então a leitura passa pelo teto antes de ser gravada. Sem isso, o cadastro era
     * a porta por onde um número implausível entrava sem conferência nenhuma e, de lá, ampliava o
     * teto do próprio chassi (ver plausibilityCeilingFor()).
     *
     * @throws ValidationException
     */
    public function registerOdometer(Vehicle $vehicle, int $kilometers, string $attribute = 'current_kilometers'): void
    {
        $this->assertOdometerKilometers($kilometers, $vehicle->year === null ? null : (int) $vehicle->year, $vehicle, $attribute);

        $vehicle->update([
            'current_kilometers' => $kilometers,
            'odometer_at_registration' => $kilometers,
        ]);
    }

    /**
     * Recalcula `vehicles.current_kilometers` a partir de `vehicle_odometer_readings`: é o maior km
     * entre as leituras que contam (VehicleOdometerLog::highestCountedKilometers). A coluna passa a
     * ser um valor derivado — cache de consulta —, e a tabela é a fonte da verdade do odômetro.
     *
     * Até aqui o cálculo era `max(odometer_at_registration, maior OS que conta)` repetido em dois
     * métodos, e quem gravava um km novo passava o número para não depender da ordem das escritas.
     * Não é mais preciso: os observers gravam a leitura dentro do save da origem, antes de qualquer
     * chamador chegar aqui, então o maior km da tabela já inclui o que acabou de ser gravado. Por
     * isso o antigo applyMaintenanceKilometers($vehicle, $kilometers) deixou de existir: um
     * parâmetro que ninguém mais precisa é um parâmetro que volta a divergir.
     *
     * **Projeção vazia não é odômetro zero.** Veículo anterior aos observers, ou que o backfill
     * ainda não alcançou, não tem leitura nenhuma — e sobrescrever a coluna com zero apagaria o
     * único lugar onde o número dele existe. Quando a tabela não tem o que dizer sobre este
     * veículo, este método não diz nada: deixa a coluna como está e espera o
     * `odometer:backfill-readings`, que é quem alcança o histórico.
     *
     * A condição é "nenhuma leitura", não "maior km igual a zero": veículo cuja única OS foi
     * contestada, sem hodômetro de cadastro, tem leitura e vale zero mesmo — e aí zero é a
     * resposta certa, não a ausência de resposta.
     */
    public function refreshCurrentKilometers(Vehicle $vehicle): void
    {
        $highest = $this->odometerLog->highestCountedKilometers($vehicle);

        if ($highest === 0 && ! $vehicle->odometerReadings()->exists()) {
            return;
        }

        $vehicle->update(['current_kilometers' => $highest]);
    }

    private function minimumAllowedKilometers(Vehicle $vehicle, ?CarbonImmutable $date, ?Maintenance $except): int
    {
        $query = $vehicle->maintenances()->countsTowardOdometer()->whereNotNull('kilometers');

        if ($except !== null) {
            $query->where('id', '!=', $except->id);
        }

        if ($date !== null) {
            $query->whereDate('maintenance_date', '<=', $date);
        }

        $floor = (int) ($query->max('kilometers') ?? 0);

        if ($date === null || $this->happensAfterRegistration($vehicle, $date)) {
            $floor = max($floor, $this->registrationKilometers($vehicle) ?? 0);
        }

        return $floor;
    }

    private function maximumAllowedKilometers(Vehicle $vehicle, ?CarbonImmutable $date, ?Maintenance $except): ?int
    {
        if ($date === null) {
            return null;
        }

        $query = $vehicle->maintenances()
            ->countsTowardOdometer()
            ->whereNotNull('kilometers')
            ->whereDate('maintenance_date', '>', $date);

        if ($except !== null) {
            $query->where('id', '!=', $except->id);
        }

        $ceiling = $query->min('kilometers');
        $ceiling = $ceiling === null ? null : (int) $ceiling;

        if (! $this->happensAfterRegistration($vehicle, $date)) {
            $registration = $this->registrationKilometers($vehicle);

            if ($registration !== null) {
                $ceiling = $ceiling === null ? $registration : min($ceiling, $registration);
            }
        }

        return $ceiling;
    }

    /**
     * Teto do que o veículo pode plausivelmente ter rodado, derivado do ano dele.
     *
     * Sem isso, `max:9999999` nas validações era o único limite: nada impedia gravar 500.000 km num
     * carro 2024. E o número não fica só no registro — ele vira o `current_kilometers` do veículo e
     * o piso de toda OS seguinte, então um dígito a mais trancava o veículo até um admin ocultar o
     * registro (MaintenancePublicVisibilityService). Recusar na entrada é mais barato que desfazer.
     *
     * A folga é deliberadamente grande, porque recusar um veículo real é pior que aceitar um número
     * alto: PLAUSIBILITY_BASE_KILOMETERS (50.000 km) cobre o veículo que sai da fábrica ou da
     * importação com km rodado, o ano-modelo à frente do calendário e o carro novo em uso intenso;
     * PLAUSIBILITY_KILOMETERS_PER_YEAR (100.000 km por ano de vida) são ~274 km por dia, todos os
     * dias, contra os 13.000 km/ano da média brasileira
     * (`config('maintenance_intervals.average_kilometers_per_year')`) e os 50.000 a 80.000 de um
     * táxi ou motorista de aplicativo. O ano-modelo conta como ano inteiro, então até um veículo do
     * ano corrente tem 150.000 km de margem. Só pega quem digitou um dígito a mais.
     *
     * **Veículo sem ano conhecido** (cadastro antigo, ou ano gravado como zero) não tem teto: não há
     * de onde derivar, e chutar um ano seria recusar por chute. Devolve null.
     */
    private function plausibilityCeiling(Vehicle $vehicle, int $floor): ?int
    {
        return $this->plausibilityCeilingFor(
            $vehicle->year === null ? null : (int) $vehicle->year,
            max($floor, $this->alreadyRunKilometers($vehicle)),
        );
    }

    /**
     * O teto a partir do ano, com a folga de um ano que mantém a faixa aceita não vazia.
     *
     * `$alreadyRun` é o que o veículo já registra (piso da data, `current_kilometers`,
     * `odometer_at_registration`) e entra no cálculo **só até onde ele mesmo passaria no teto do
     * ano**. Esse recorte é o ponto: o termo existia para não recusar quem roda muito — parar
     * exatamente na leitura atual deixaria a faixa vazia, e o veículo não somaria nem um km —, mas
     * sem limite ele virava uma catraca. Cada leitura gravada subia o teto em mais um ano de folga,
     * e como o hodômetro do cadastro não passava por teto nenhum, cadastrar um veículo do ano com
     * 9.999.999 km levava o teto daquele chassi para além de qualquer `max:9999999`: o teto deixava
     * de existir nas quatro superfícies de OS, para sempre. Um número que nunca passaria na
     * validação não pode virar o piso que amplia a validação.
     *
     * Com o recorte, o teto é função do ano e só dele: `[fromYear, fromYear + um ano de folga]`. A
     * folga continua valendo para o veículo que legitimamente chegou ao topo da faixa do seu ano, e
     * deixa de acumular por registro. O preço aceito é o veículo que roda mais de
     * PLAUSIBILITY_KILOMETERS_PER_YEAR acima do teto do ano dentro do mesmo ano-calendário: ele
     * espera a virada do ano (que sobe o teto) ou a correção do ano do cadastro. Em troca, nenhum
     * valor já gravado — legado, ou de uma porta que a gente ainda não conheça — amplia o teto.
     */
    private function plausibilityCeilingFor(?int $year, int $alreadyRun): ?int
    {
        $year = (int) ($year ?? 0);

        if ($year < 1900) {
            return null;
        }

        $yearsOfLife = max(0, CarbonImmutable::now()->year - $year) + 1;
        $fromYear = self::PLAUSIBILITY_BASE_KILOMETERS + ($yearsOfLife * self::PLAUSIBILITY_KILOMETERS_PER_YEAR);

        return max(
            $fromYear,
            min(max(0, $alreadyRun), $fromYear) + self::PLAUSIBILITY_KILOMETERS_PER_YEAR,
        );
    }

    /**
     * O que o veículo já registra como hodômetro. Veículo que ainda não existe (cadastro) não
     * registra nada.
     */
    private function alreadyRunKilometers(?Vehicle $vehicle): int
    {
        if ($vehicle === null) {
            return 0;
        }

        return max(
            (int) ($vehicle->current_kilometers ?? 0),
            (int) ($vehicle->odometer_at_registration ?? 0),
        );
    }

    /**
     * Veículo criado pela oficina pelo chassi nasce sem hodômetro de cadastro e sem placa: não há
     * leitura "no cadastro" a usar de teto, e o km corrente (que só cresce com as OS) travaria o
     * registro de serviços antigos.
     */
    private function registrationKilometers(Vehicle $vehicle): ?int
    {
        if ($vehicle->odometer_at_registration === null && $vehicle->license_plate === null) {
            return null;
        }

        $kilometers = $vehicle->odometer_at_registration ?? $vehicle->current_kilometers;

        return $kilometers === null ? null : (int) $kilometers;
    }

    private function happensAfterRegistration(Vehicle $vehicle, CarbonImmutable $date): bool
    {
        if ($vehicle->created_at === null) {
            return true;
        }

        return $date->greaterThanOrEqualTo(
            CarbonImmutable::parse($vehicle->created_at)->startOfDay(),
        );
    }

    private function normalizeDate(CarbonInterface|string|null $maintenanceDate): ?CarbonImmutable
    {
        if ($maintenanceDate === null || $maintenanceDate === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($maintenanceDate)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
