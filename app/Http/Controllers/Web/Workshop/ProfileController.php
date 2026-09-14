<?php

namespace App\Http\Controllers\Web\Workshop;

use App\Http\Controllers\Controller;
use App\Models\Workshop;
use App\Services\Workshop\WorkshopLogoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\File;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        private WorkshopLogoService $logos,
    ) {}

    public function show(Request $request): View
    {
        $workshop = $request->user()->workshop;

        return view('workshop.profile.show', compact('workshop'));
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
        $data['cep'] = preg_replace('/\D/', '', $data['cep']);
        $data['state'] = strtoupper($data['state']);

        $logo = $request->file('logo');
        unset($data['logo']);

        $workshop = Workshop::create($data);

        if ($logo !== null) {
            $this->logos->store($workshop, $logo);
        }

        return redirect()->route('workshop.dashboard')
            ->with('success', 'Oficina cadastrada com sucesso!');
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
        if (isset($data['cep'])) {
            $data['cep'] = preg_replace('/\D/', '', $data['cep']);
        }
        if (isset($data['state'])) {
            $data['state'] = strtoupper($data['state']);
        }

        $logo = $request->file('logo');
        unset($data['logo']);

        $workshop->update($data);

        if ($logo !== null) {
            $this->logos->store($workshop, $logo);
        }

        return redirect()->route('workshop.profile.show')
            ->with('success', 'Dados da oficina atualizados!');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateWorkshop(Request $request, bool $partial = false, bool $logo = false): array
    {
        $rules = [
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'phone' => [$partial ? 'sometimes' : 'required', 'string', 'max:20'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'facebook' => ['nullable', 'url', 'max:255'],
            'instagram' => ['nullable', 'url', 'max:255'],
            'cep' => [$partial ? 'sometimes' : 'required', 'string', 'size:8'],
            'street' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'number' => [$partial ? 'sometimes' : 'required', 'string', 'max:20'],
            'complement' => ['nullable', 'string', 'max:255'],
            'neighborhood' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'city' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'state' => [$partial ? 'sometimes' : 'required', 'string', 'size:2'],
        ];

        if ($logo) {
            $rules['logo'] = [
                'nullable',
                File::image(allowSvg: false)
                    ->types(['jpg', 'jpeg', 'png', 'webp'])
                    ->max(2 * 1024),
            ];
        }

        return $request->validate($rules);
    }
}
