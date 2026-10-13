<?php

namespace App\Support\Garage;

use App\Models\User;

/**
 * Linhas de identificação da loja para os e-mails que falam com o proprietário de um veículo em
 * consignação: endereço, telefone e e-mail, como cadastrados em "Minha loja" (com o fallback de
 * User::storeIdentity para a conta). O proprietário precisa saber com quem falar sem depender de
 * responder ao e-mail da plataforma.
 */
final class StoreContactLines
{
    /**
     * @return list<string>
     */
    public static function for(User $garageUser): array
    {
        $identity = $garageUser->storeIdentity();
        $contact = [];

        if ($identity['address'] !== null) {
            $contact[] = $identity['address'];
        }

        if (filled($identity['phone'])) {
            $contact[] = 'telefone '.$identity['phone'];
        }

        if (filled($identity['email'])) {
            $contact[] = $identity['email'];
        }

        return $contact === [] ? [] : ['Contato da loja: '.implode(' · ', $contact).'.'];
    }
}
