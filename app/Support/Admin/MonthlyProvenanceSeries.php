<?php

namespace App\Support\Admin;

use App\Models\Maintenance;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Manutenções por mês do serviço (maintenance_date), divididas pela procedência: Selo da oficina
 * (verified_at preenchido) e Declaradas. Alimenta o gráfico "Manutenções por mês" da Visão geral
 * do admin. Uma consulta agrupada; meses sem manutenção entram com zero, para o eixo não pular mês.
 *
 * Ex.: app(MonthlyProvenanceSeries::class)->lastMonths(12)
 */
final class MonthlyProvenanceSeries
{
    /**
     * Os últimos $months meses até o mês de $now (inclusive), do mais antigo para o mais recente.
     *
     * @return list<array{month: string, label: string, long_label: string, sealed: int, declared: int, total: int}>
     */
    public function lastMonths(int $months = 12, ?CarbonInterface $now = null): array
    {
        $months = max(1, $months);
        $reference = CarbonImmutable::instance($now ?? now());
        $firstMonth = $reference->startOfMonth()->subMonthsNoOverflow($months - 1);
        $monthExpression = $this->monthExpression();

        $totalsByMonth = Maintenance::query()
            ->toBase()
            ->selectRaw($monthExpression.' as month_key')
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when verified_at is not null then 1 else 0 end) as sealed')
            ->whereDate('maintenance_date', '>=', $firstMonth->toDateString())
            ->whereDate('maintenance_date', '<=', $reference->endOfMonth()->toDateString())
            ->groupByRaw($monthExpression)
            ->get()
            ->keyBy('month_key');

        $series = [];

        for ($offset = 0; $offset < $months; $offset++) {
            $month = $firstMonth->addMonthsNoOverflow($offset);
            $row = $totalsByMonth->get($month->format('Y-m'));
            $total = (int) ($row->total ?? 0);
            $sealed = (int) ($row->sealed ?? 0);

            $series[] = [
                'month' => $month->format('Y-m'),
                'label' => Str::ucfirst(rtrim($month->locale('pt_BR')->translatedFormat('M'), '.')),
                'long_label' => Str::ucfirst($month->locale('pt_BR')->translatedFormat('F \d\e Y')),
                'sealed' => $sealed,
                'declared' => $total - $sealed,
                'total' => $total,
            ];
        }

        return $series;
    }

    /**
     * "AAAA-MM" da data do serviço no SQL de cada banco (SQLite nos testes, PostgreSQL em produção).
     */
    private function monthExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "to_char(maintenance_date, 'YYYY-MM')",
            'mysql', 'mariadb' => "date_format(maintenance_date, '%Y-%m')",
            default => "strftime('%Y-%m', maintenance_date)",
        };
    }
}
