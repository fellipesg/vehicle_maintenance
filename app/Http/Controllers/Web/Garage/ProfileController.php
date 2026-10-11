<?php

namespace App\Http\Controllers\Web\Garage;

use App\Http\Controllers\Controller;
use App\Models\Garage;
use App\Models\User;
use App\Services\Garage\GarageReview;
use App\Services\TenantService;
use App\Support\BrazilianStates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * "Minha loja" (/garagem/minha-loja): CNPJ, contato e endereço da garagem, em uma página só — a
 * loja não tem perfil público como a oficina, então não há o par ver/editar.
 *
 * O CNPJ é o campo que importa para o estoque: sem ele VehicleEntryFlow::ownershipFor devolve
 * missing_account_document e todo CRLV-e entra como consignação, nunca como veículo da loja.
 */
class ProfileController extends Controller
{
    public function __construct(
        private TenantService $tenants,
        private GarageReview $review,
    ) {}

    public function show(Request $request): View
    {
        return view('garage.profile.edit', [
            'garage' => $this->garageFor($request->user()),
            'stateOptions' => BrazilianStates::options(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $garage = $this->garageFor($request->user());
        $documentBefore = $garage->normalizedDocument();

        $garage->update($this->validated($request));

        // CNPJ novo, análise nova: sem isto, aprovar o CNPJ A e trocar para B no dia seguinte
        // tornaria a aprovação inútil.
        $backToReview = $garage->normalizedDocument() !== $documentBefore
            && $this->review->resetForDocumentChange($garage);

        return redirect()->route('garage.profile.show')->with(
            'success',
            $backToReview
                ? 'Dados da loja atualizados. Como o CNPJ mudou, a loja voltou para análise.'
                : 'Dados da loja atualizados.',
        );
    }

    /**
     * A linha de garages de quem está logado, criada na hora se faltar: conta virada para 'garage'
     * depois do cadastro não passou por TenantService e pode não ter nenhuma.
     */
    private function garageFor(User $user): Garage
    {
        if ($user->tenant_id === null) {
            $this->tenants->createForUser($user);
            $user->refresh();
        }

        return $user->garage()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'tenant_id' => $user->tenant_id,
                'name' => $user->name,
                'document' => $user->document,
                'phone' => $user->phone,
                'email' => $user->email,
            ],
        );
    }

    /**
     * Normaliza antes de validar: CNPJ, telefone e CEP só com dígitos ("01310-100" vira
     * "01310100") e UF em maiúsculas, como em Minha oficina.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $this->normalizeInput($request);

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'document' => ['nullable', 'string', 'digits:14'],
            'phone' => ['nullable', 'string', 'digits_between:10,11'],
            'email' => ['nullable', 'email', 'max:255'],
            'cep' => ['nullable', 'string', 'digits:8'],
            'street' => ['nullable', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:255'],
            'complement' => ['nullable', 'string', 'max:255'],
            'neighborhood' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'size:2', Rule::in(BrazilianStates::codes())],
        ], [
            'document.digits' => 'Informe o CNPJ da loja com 14 dígitos.',
            'phone.digits_between' => 'Informe o telefone com DDD (10 ou 11 dígitos).',
            'cep.digits' => 'Informe o CEP com 8 dígitos.',
            'state.in' => 'Escolha a UF da lista.',
        ]);
    }

    private function normalizeInput(Request $request): void
    {
        $normalized = [];

        foreach (['document', 'phone', 'cep'] as $field) {
            if ($request->filled($field) && is_scalar($request->input($field))) {
                $normalized[$field] = preg_replace('/\D/', '', (string) $request->input($field));
            }
        }

        if ($request->filled('state') && is_scalar($request->input('state'))) {
            $normalized['state'] = strtoupper(trim((string) $request->input('state')));
        }

        $request->merge($normalized);
    }
}
