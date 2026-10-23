<?php

namespace App\Features;

/**
 * Fotos das manutenções (envio, visualização e fotos no PDF do histórico).
 *
 * Desligada para todo mundo por padrão: um admin liga conta a conta (proprietário), oficina ou
 * loja pelo painel. O escopo do Pennant é o model que o admin liga (User, Workshop ou Garage);
 * quem decide se um usuário tem a funcionalidade é App\Support\Maintenance\MaintenancePhotoAccess,
 * que soma os três. Pergunte sempre por lá, nunca direto ao Pennant, para o escopo não divergir.
 *
 * O Pennant é o mecanismo das próximas funcionalidades ligadas por conta: nova classe em
 * app/Features, resolvendo para false.
 */
class MaintenancePhotos
{
    /**
     * Nome gravado na tabela features (estável, não muda se a classe mudar de namespace).
     */
    public string $name = 'maintenance-photos';

    /**
     * Desligada até um admin ligar. Ninguém ganha a funcionalidade por existir.
     */
    public function resolve(mixed $scope): bool
    {
        return false;
    }
}
