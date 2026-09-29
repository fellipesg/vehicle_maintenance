<?php

namespace App\Http\Controllers\Web\Workshop;

use App\Http\Controllers\Controller;
use App\Models\Workshop;
use App\Services\Workshop\WorkshopLogoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Siglas das 27 unidades da federação, na ordem alfabética do nome do estado.
     *
     * @var array<string, string>
     */
    public const STATES = [
        'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas', 'BA' => 'Bahia',
        'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo', 'GO' => 'Goiás',
        'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul', 'MG' => 'Minas Gerais',
        'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná', 'PE' => 'Pernambuco', 'PI' => 'Piauí',
        'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte', 'RS' => 'Rio Grande do Sul',
        'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina', 'SP' => 'São Paulo',
        'SE' => 'Sergipe', 'TO' => 'Tocantins',
    ];

    public function __construct(
        private WorkshopLogoService $logos,
    ) {}

    public function show(Request $request): View
    {
        $workshop = $request->user()->workshop;
        $hasActiveTemplate = $workshop?->warrantyTemplates()->where('is_active', true)->exists() ?? false;

        return view('workshop.profile.show', compact('workshop', 'hasActiveTemplate'));
    }

    public function create(): View
    {
        return view('workshop.profile.create');
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->workshop) {
            return redirect()->route('workshop.profile.show');
        }

        $data = $this->validateWorkshop($request, logo: true);
        $data['user_id'] = $request->user()->id;
        $data['tenant_id'] = $request->user()->tenant_id;
        $data['whatsapp'] = $data['whatsapp'] ?? $data['phone'];

        $logo = $request->file('logo');
        unset($data['logo']);

        $workshop = Workshop::create($data);

        if ($logo !== null) {
            $this->logos->store($workshop, $logo);
        }

        return redirect()->route('workshop.dashboard')
            ->with('success', 'Oficina cadastrada.');
    }

    public function edit(Request $request): View
    {
        $workshop = $request->user()->workshop;

        if (! $workshop) {
            return view('workshop.profile.create');
        }

        return view('workshop.profile.edit', compact('workshop'));
    }

    public function update(Request $request): RedirectResponse
    {
        $workshop = $request->user()->workshop;

        if (! $workshop) {
            return redirect()->route('workshop.profile.create');
        }

        $data = $this->validateWorkshop($request, partial: true, logo: true);
        $data['whatsapp'] = $data['whatsapp'] ?? ($data['phone'] ?? $workshop->phone);

        $logo = $request->file('logo');
        unset($data['logo']);

        $workshop->update($data);

        if ($logo !== null) {
            $this->logos->store($workshop, $logo);
        }

        return redirect()->route('workshop.profile.show')
            ->with('success', 'Dados da oficina atualizados.');
    }

    /**
     * Normaliza antes de validar: CEP e telefones só com dígitos ("01310-100" vira "01310100"),
     * UF em maiúsculas e redes sociais aceitando "@perfil" ou o link.
     *
     * @return array<string, mixed>
     */
    private function validateWorkshop(Request $request, bool $partial = false, bool $logo = false): array
    {
        $this->normalizeInput($request);

        $rules = [
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'phone' => [$partial ? 'sometimes' : 'required', 'string', 'digits_between:10,13'],
            'whatsapp' => ['nullable', 'string', 'digits_between:10,13'],
            'email' => ['nullable', 'email', 'max:255'],
            'facebook' => ['nullable', 'url', 'max:255'],
            'instagram' => ['nullable', 'url', 'max:255'],
            'cep' => [$partial ? 'sometimes' : 'required', 'string', 'size:8'],
            'street' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'number' => [$partial ? 'sometimes' : 'required', 'string', 'max:20'],
            'complement' => ['nullable', 'string', 'max:255'],
            'neighborhood' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'city' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'state' => [$partial ? 'sometimes' : 'required', 'string', 'size:2', Rule::in(array_keys(self::STATES))],
        ];

        if ($logo) {
            $rules['logo'] = [
                'nullable',
                File::image(allowSvg: false)
                    ->types(['jpg', 'jpeg', 'png', 'webp'])
                    ->max(2 * 1024),
            ];
        }

        return $request->validate($rules, [
            'state.in' => 'Escolha a UF da lista.',
            'phone.digits_between' => 'Informe o telefone com DDD (10 ou 11 dígitos).',
            'whatsapp.digits_between' => 'Informe o WhatsApp com DDD (10 ou 11 dígitos).',
        ]);
    }

    private function normalizeInput(Request $request): void
    {
        $normalized = [];

        foreach (['cep', 'phone', 'whatsapp'] as $field) {
            if ($request->filled($field) && is_scalar($request->input($field))) {
                $normalized[$field] = preg_replace('/\D/', '', (string) $request->input($field));
            }
        }

        if ($request->filled('state') && is_scalar($request->input('state'))) {
            $normalized['state'] = strtoupper(trim((string) $request->input('state')));
        }

        foreach (['instagram' => 'instagram.com', 'facebook' => 'facebook.com'] as $field => $host) {
            if ($request->filled($field) && is_scalar($request->input($field))) {
                $normalized[$field] = self::socialUrl((string) $request->input($field), $host);
            }
        }

        $request->merge($normalized);
    }

    /**
     * "@minhaoficina" e "instagram.com/minhaoficina" (sem https) viram
     * "https://www.instagram.com/minhaoficina". Link completo e qualquer outro texto ficam como
     * estão (e a validação de URL decide).
     */
    public static function socialUrl(string $value, string $host): string
    {
        $value = trim($value);

        if (preg_match('/^@([A-Za-z0-9._-]+)$/', $value, $handle) === 1) {
            return 'https://www.'.$host.'/'.$handle[1];
        }

        if (preg_match('#^(?:www\.)?'.preg_quote($host, '#').'/(.+)$#i', $value, $path) === 1) {
            return 'https://www.'.$host.'/'.ltrim($path[1], '@/');
        }

        return $value;
    }
}
