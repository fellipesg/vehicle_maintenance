<?php

namespace App\Http\Requests\Api\V1;

/**
 * Mensagens do cadastro pelo CRLV-e no app, em pt-BR para a tela mostrar como vieram.
 */
final class VehicleCrlvMessages
{
    public const EXPIRED = 'A leitura do CRLV-e expirou. Envie o documento de novo.';

    public const POWER_OF_ATTORNEY_ON_WEB = 'O CRLV-e não está no CPF/CNPJ da sua conta, então este veículo precisa de procuração. Termine o cadastro pelo site da RevisaLog.';
}
