<?php

namespace App\Support\Inspection;

use App\Enums\InspectionFinding;
use App\Enums\InspectionFindingStatus;

/**
 * O mapa de achados de um laudo (vehicle_inspections.findings): um registro por eixo de
 * App\Enums\InspectionFinding, sempre todos, sempre na mesma ordem.
 *
 * Sempre todos de propósito. Eixo ausente seria ambíguo — "não olhei" e "olhei e está bem" viram a
 * mesma coisa —, e é justamente a diferença que um laudo precisa sustentar. Eixo que o vistoriador
 * não mandou nasce `not_assessed`.
 */
final class InspectionFindings
{
    /**
     * Teto da observação por eixo. Observação é apontamento, não relatório: o relatório é o PDF.
     */
    public const MAX_NOTE_LENGTH = 500;

    /**
     * Mapa completo e saneado, pronto para gravar. Chave desconhecida é descartada, status
     * desconhecido vira `not_assessed`, observação vazia vira null.
     *
     * @return array<string, array{status: string, note: string|null}>
     */
    public static function normalize(mixed $input): array
    {
        $raw = is_array($input) ? $input : [];
        $findings = [];

        foreach (InspectionFinding::cases() as $axis) {
            $entry = $raw[$axis->value] ?? null;
            $entry = is_array($entry) ? $entry : [];

            $status = $entry['status'] ?? null;
            $status = is_string($status)
                ? (InspectionFindingStatus::tryFrom($status) ?? InspectionFindingStatus::NotAssessed)
                : InspectionFindingStatus::NotAssessed;

            $findings[$axis->value] = [
                'status' => $status->value,
                'note' => self::normalizeNote($entry['note'] ?? null),
            ];
        }

        return $findings;
    }

    public static function statusOf(mixed $findings, InspectionFinding $axis): InspectionFindingStatus
    {
        $entry = is_array($findings) ? ($findings[$axis->value] ?? null) : null;
        $status = is_array($entry) ? ($entry['status'] ?? null) : null;

        return is_string($status)
            ? (InspectionFindingStatus::tryFrom($status) ?? InspectionFindingStatus::NotAssessed)
            : InspectionFindingStatus::NotAssessed;
    }

    public static function noteOf(mixed $findings, InspectionFinding $axis): ?string
    {
        $entry = is_array($findings) ? ($findings[$axis->value] ?? null) : null;
        $note = is_array($entry) ? ($entry['note'] ?? null) : null;

        return self::normalizeNote($note);
    }

    /**
     * Eixos reprovados. É o que o veredito "Aprovado" não pode esconder.
     *
     * @return list<InspectionFinding>
     */
    public static function failing(mixed $findings): array
    {
        return array_values(array_filter(
            InspectionFinding::cases(),
            fn (InspectionFinding $axis): bool => self::statusOf($findings, $axis) === InspectionFindingStatus::Fail,
        ));
    }

    /**
     * Eixos com algo a dizer (atenção ou reprovado). É o que a forma mínima mostra a quem não é dono.
     *
     * @return list<InspectionFinding>
     */
    public static function reportable(mixed $findings): array
    {
        return array_values(array_filter(
            InspectionFinding::cases(),
            fn (InspectionFinding $axis): bool => self::statusOf($findings, $axis)->isReportable(),
        ));
    }

    private static function normalizeNote(mixed $note): ?string
    {
        if (! is_string($note)) {
            return null;
        }

        $note = trim($note);

        if ($note === '') {
            return null;
        }

        return mb_substr($note, 0, self::MAX_NOTE_LENGTH);
    }
}
