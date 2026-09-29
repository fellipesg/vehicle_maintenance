<?php

namespace App\Support\Vehicle;

use App\Enums\Portal;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Vehicle\VehicleOwnershipService;
use InvalidArgumentException;

/**
 * Assistente de entrada de veículo: "Adicionar veículo" no portal do Proprietário e "Adicionar ao
 * estoque" no do Lojista. As telas são as mesmas (resources/views/vehicles/entry) e os passos também:
 *
 * 1. Documento: o CRLV-e é a ação principal; o formulário manual fica recolhido.
 * 2. Conferir: veículo novo, ou vínculo a um veículo que já está na RevisaLog. No lojista, quando o
 *    CRLV-e não está no CPF/CNPJ da conta, o passo seguinte é a procuração (consignação), e depois a
 *    análise da equipe.
 * 3. Capas (opcional): paisagem 16:9 e retrato 9:16, recortadas antes do envio.
 *
 * As rotas antigas de vínculo (GET .../vincular) só redirecionam para o passo 1: a leitura do CRLV-e
 * já descobre sozinha se o veículo é novo ou existente. Rotas, rótulos e mensagens de cada portal
 * saem daqui; controllers e views não testam o portal por conta própria.
 */
final class VehicleEntryFlow
{
    public const STEP_DOCUMENT = 'document';

    public const STEP_REVIEW = 'review';

    public const STEP_POWER_OF_ATTORNEY = 'power_of_attorney';

    public const STEP_ANALYSIS = 'analysis';

    public const STEP_COVERS = 'covers';

    /**
     * O CRLV-e está no CPF/CNPJ de quem envia (ou é o Proprietário, que fica como dono, a não ser no
     * vínculo a um veículo que hoje é de outra conta).
     */
    public const OWNERSHIP_OWNER = 'owner';

    /**
     * A conta do lojista não tem CPF/CNPJ: não dá para confirmar a posse, e o veículo vira consignação.
     */
    public const OWNERSHIP_MISSING_ACCOUNT_DOCUMENT = 'missing_account_document';

    /**
     * O CRLV-e está no CPF/CNPJ de outra pessoa: consignação, com a procuração do proprietário.
     */
    public const OWNERSHIP_OTHER_OWNER = 'other_owner';

    /**
     * Limite do CRLV-e e da procuração (PDF), em KB: o max: da validação.
     */
    public const DOCUMENT_MAX_KILOBYTES = 10240;

    /**
     * Limite de cada capa, em KB: o max: da validação.
     */
    public const COVER_MAX_KILOBYTES = 5120;

    private function __construct(public readonly Portal $portal) {}

    public static function for(Portal $portal): self
    {
        if (! in_array($portal, [Portal::Owner, Portal::Dealer], true)) {
            throw new InvalidArgumentException("O assistente de veículo existe só no Proprietário e no Lojista, não em {$portal->label()}.");
        }

        return new self($portal);
    }

    public function isDealer(): bool
    {
        return $this->portal === Portal::Dealer;
    }

    /**
     * Nome da rota do passo no portal: 'create' vira user.vehicles.create ou garage.vehicles.create.
     */
    public function routeName(string $action): string
    {
        return ($this->isDealer() ? 'garage.vehicles.' : 'user.vehicles.').$action;
    }

    /**
     * @param  mixed  $parameters  parâmetros de route()
     */
    public function url(string $action, mixed $parameters = []): string
    {
        return route($this->routeName($action), $parameters);
    }

    /**
     * Nome do assistente, igual ao CTA do portal (Portal::primaryAction()): H1 do passo 1.
     */
    public function title(): string
    {
        return $this->isDealer() ? 'Adicionar ao estoque' : 'Adicionar veículo';
    }

    /**
     * Botão que grava o veículo novo no passo Conferir.
     */
    public function addLabel(): string
    {
        return $this->title();
    }

    public function listLabel(): string
    {
        return $this->isDealer() ? 'Estoque' : 'Meus veículos';
    }

    public function listUrl(): string
    {
        return $this->url('index');
    }

    public function vehicleUrl(Vehicle $vehicle): string
    {
        return $this->url('show', $vehicle);
    }

    /**
     * Descrição do passo 1.
     */
    public function documentDescription(): string
    {
        return $this->isDealer()
            ? 'Envie o CRLV-e digital: lemos os dados, conferimos se o veículo já está na RevisaLog e se ele é da loja ou está em consignação.'
            : 'Envie o CRLV-e digital: lemos os dados e conferimos se o veículo já está na RevisaLog.';
    }

    /**
     * H1 do passo Conferir quando o veículo já existe.
     */
    public function claimTitle(): string
    {
        return $this->isDealer() ? 'Vincular veículo ao estoque' : 'Vincular veículo à sua conta';
    }

    /**
     * Botão que confirma o vínculo.
     */
    public function claimLabel(): string
    {
        return $this->isDealer() ? 'Vincular ao estoque' : 'Vincular à minha conta';
    }

    /**
     * Contexto do passo da procuração, acima do H1.
     */
    public function powerOfAttorneyEyebrow(): string
    {
        return $this->isDealer() ? 'Consignação' : 'Documento em nome de outra pessoa';
    }

    /**
     * Onde o veículo aparece enquanto a procuração está em análise (lista "Como funciona").
     */
    public function pendingPlacement(): string
    {
        return $this->isDealer()
            ? 'Enquanto isso, o veículo aparece no estoque como "Procuração em análise".'
            : 'Enquanto isso, o histórico fica fechado.';
    }

    /**
     * Onde o veículo aparece depois da aprovação (lista "Como funciona").
     */
    public function approvedPlacement(): string
    {
        return $this->isDealer()
            ? 'com a aprovação, a ficha e o histórico de manutenções abrem pelo estoque. Se a procuração for recusada, o motivo aparece no estoque e você pode reenviar.'
            : 'com a aprovação, a ficha e o histórico de manutenções abrem em Meus veículos.';
    }

    /**
     * Etapas para <x-ui.stepper>. A consignação troca "Capas" pela procuração e pela análise da equipe.
     *
     * @return array{items: list<array{label: string, description?: string}>, current: int}
     */
    public function steps(string $current, bool $consignment = false): array
    {
        $steps = $consignment
            ? [
                self::STEP_DOCUMENT => ['label' => 'Documento'],
                self::STEP_REVIEW => ['label' => 'Conferir'],
                self::STEP_POWER_OF_ATTORNEY => ['label' => 'Procuração'],
                self::STEP_ANALYSIS => ['label' => 'Análise da equipe'],
            ]
            : [
                self::STEP_DOCUMENT => ['label' => 'Documento'],
                self::STEP_REVIEW => ['label' => 'Conferir'],
                self::STEP_COVERS => ['label' => 'Capas', 'description' => 'opcional'],
            ];

        $position = array_search($current, array_keys($steps), true);

        return [
            'items' => array_values($steps),
            'current' => $position === false ? 1 : $position + 1,
        ];
    }

    /**
     * Resultado esperado da posse antes de confirmar, para avisar na tela Conferir. A regra que vale
     * na gravação é a de VehicleOwnershipService::resolveOwnershipType() (veículo novo) e
     * resolveClaimOwnershipType() (vínculo, $claimedVehicle); esta só antecipa. No Proprietário, o
     * CPF/CNPJ só conta no vínculo a um veículo que hoje é de outra conta.
     *
     * @param  array<string, mixed>|null  $crlvPreview  o CRLV-e lido (crlv_verification.parsed)
     */
    public function ownershipFor(User $user, ?array $crlvPreview, ?Vehicle $claimedVehicle = null): string
    {
        if (! $this->isDealer()
            && ($claimedVehicle === null || ! VehicleOwnershipService::hasCurrentOwnerOtherThan($user, $claimedVehicle))) {
            return self::OWNERSHIP_OWNER;
        }

        $accountDocument = $user->normalizedDocument();

        if ($accountDocument === null) {
            return self::OWNERSHIP_MISSING_ACCOUNT_DOCUMENT;
        }

        $crlvDocument = preg_replace('/\D/', '', (string) ($crlvPreview['owner_document'] ?? '')) ?: null;

        return $crlvDocument !== null && $crlvDocument === $accountDocument
            ? self::OWNERSHIP_OWNER
            : self::OWNERSHIP_OTHER_OWNER;
    }

    /**
     * Motivo da consignação, guardado no passo da procuração: conta sem CPF/CNPJ ou CRLV-e de outra
     * pessoa. As duas situações pedem ações diferentes, então têm mensagens diferentes.
     */
    public static function consignmentReasonFor(User $user): string
    {
        return $user->normalizedDocument() === null
            ? self::OWNERSHIP_MISSING_ACCOUNT_DOCUMENT
            : self::OWNERSHIP_OTHER_OWNER;
    }

    /**
     * Aviso de sucesso depois de gravar o veículo novo (o assistente segue para as capas).
     */
    public function addedMessage(bool $withCrlv): string
    {
        if ($withCrlv) {
            return $this->isDealer() ? 'Veículo adicionado ao estoque.' : 'Veículo adicionado à sua conta.';
        }

        return $this->isDealer()
            ? 'Veículo adicionado ao estoque sem o CRLV-e: a propriedade fica sem confirmação.'
            : 'Veículo adicionado. Para confirmar a propriedade, importe o CRLV-e depois, em Editar veículo.';
    }

    public function claimedMessage(): string
    {
        return $this->isDealer() ? 'Veículo vinculado ao estoque.' : 'Veículo vinculado à sua conta.';
    }

    /**
     * Aviso depois do envio da procuração.
     */
    public function consignmentSentMessage(bool $newVehicle): string
    {
        if ($this->isDealer()) {
            return $newVehicle
                ? 'Veículo adicionado em consignação. Ele aparece no estoque como "Procuração em análise" até a aprovação.'
                : 'Procuração enviada para análise. O veículo aparece no estoque como "Procuração em análise" até a aprovação.';
        }

        return $newVehicle
            ? 'Veículo adicionado com procuração. O histórico fica disponível depois da análise da equipe.'
            : 'Procuração enviada. O histórico fica disponível depois da análise da equipe.';
    }

    public function consignmentCancelledMessage(): string
    {
        return $this->isDealer()
            ? 'Envio da procuração cancelado. Nenhum veículo foi adicionado ao estoque.'
            : 'Envio da procuração cancelado. Nenhum veículo foi adicionado à sua conta.';
    }

    /**
     * Mensagem quando o veículo digitado já está na RevisaLog: o vínculo pede o CRLV-e.
     */
    public function vehicleExistsMessage(string $identifier): string
    {
        $destination = $this->isDealer() ? 'ao estoque' : 'à sua conta';

        return "Já existe um veículo com {$identifier} na RevisaLog. Envie o CRLV-e dele para vinculá-lo {$destination}.";
    }
}
