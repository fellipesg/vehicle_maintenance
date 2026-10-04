<?php

namespace App\Services\Workshop;

use App\Models\Maintenance;
use App\Models\WorkshopLead;

/**
 * Guarda as oficinas citadas só pelo nome (sem conta no RevisaLog) em manutenções declaradas: uma
 * linha por nome normalizado + cidade de quem declarou, com quantas vezes foi citada. É a fila de
 * convites do admin (Admin\WorkshopLeadController).
 */
class WorkshopLeadRecorder
{
    public function record(Maintenance $maintenance): ?WorkshopLead
    {
        $name = trim((string) $maintenance->workshop_name);

        if ($maintenance->workshop_id !== null || $maintenance->workshop_lead_id !== null || $name === '') {
            return null;
        }

        $normalized = WorkshopLead::normalizeName($name);

        if ($normalized === '') {
            return null;
        }

        $declarant = $maintenance->user;
        $city = filled($declarant?->city) ? trim((string) $declarant->city) : null;
        $state = filled($declarant?->state) ? mb_strtoupper(mb_substr(trim((string) $declarant->state), 0, 2)) : null;

        $lead = WorkshopLead::query()
            ->where('normalized_name', $normalized)
            ->when($city !== null, fn ($query) => $query->whereRaw('lower(city) = ?', [mb_strtolower($city)]), fn ($query) => $query->whereNull('city'))
            ->first();

        if ($lead === null) {
            $lead = WorkshopLead::query()->create([
                'name' => $name,
                'normalized_name' => $normalized,
                'city' => $city,
                'state' => $state,
                'mentions_count' => 0,
            ]);
        }

        $lead->forceFill([
            'mentions_count' => $lead->mentions_count + 1,
            'last_mentioned_at' => now(),
        ])->save();

        $maintenance->forceFill(['workshop_lead_id' => $lead->id])->save();

        return $lead;
    }

    /**
     * Contato da oficina que o cliente informou no formulário (e-mail ou telefone). Só preenche o
     * que a oficina citada ainda não tem.
     */
    public function rememberContact(Maintenance $maintenance, ?string $contact): void
    {
        $contact = trim((string) $contact);
        $lead = $maintenance->workshopLead;

        if ($contact === '' || $lead === null) {
            return;
        }

        if (filter_var($contact, FILTER_VALIDATE_EMAIL) !== false) {
            if ($lead->contact_email === null) {
                $lead->forceFill(['contact_email' => mb_strtolower($contact)])->save();
            }

            return;
        }

        $digits = preg_replace('/\D+/', '', $contact) ?? '';

        if (strlen($digits) >= 10 && strlen($digits) <= 13 && $lead->contact_phone === null) {
            $lead->forceFill(['contact_phone' => $digits])->save();
        }
    }
}
