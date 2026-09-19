<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Workshop;
use Illuminate\Database\Seeder;

class DemoAdminMapAddressesSeeder extends Seeder
{
    /**
     * @var list<array{street: string, number: string, neighborhood: string, city: string, state: string, cep: string}>
     */
    private array $workshopAddresses = [
        [
            'street' => 'Av. Ayrton Senna da Silva',
            'number' => '500',
            'neighborhood' => 'Gleba Fazenda Palhano',
            'city' => 'Londrina',
            'state' => 'PR',
            'cep' => '86050000',
        ],
        [
            'street' => 'Av. Paulista',
            'number' => '1000',
            'neighborhood' => 'Bela Vista',
            'city' => 'São Paulo',
            'state' => 'SP',
            'cep' => '01310100',
        ],
        [
            'street' => 'Rua XV de Novembro',
            'number' => '100',
            'neighborhood' => 'Centro',
            'city' => 'Curitiba',
            'state' => 'PR',
            'cep' => '80020310',
        ],
        [
            'street' => 'Av. Higienópolis',
            'number' => '1200',
            'neighborhood' => 'Centro',
            'city' => 'Londrina',
            'state' => 'PR',
            'cep' => '86020000',
        ],
    ];

    /**
     * @var list<array{city: string, state: string, postal_code: string, street: string, number: string}>
     */
    private array $userAddresses = [
        ['city' => 'Londrina', 'state' => 'PR', 'postal_code' => '86010000', 'street' => 'Rua Piauí', 'number' => '500'],
        ['city' => 'Maringá', 'state' => 'PR', 'postal_code' => '87013000', 'street' => 'Av. Colombo', 'number' => '800'],
        ['city' => 'Curitiba', 'state' => 'PR', 'postal_code' => '80010000', 'street' => 'Rua Marechal Deodoro', 'number' => '200'],
        ['city' => 'São Paulo', 'state' => 'SP', 'postal_code' => '01001000', 'street' => 'Rua Augusta', 'number' => '1500'],
        ['city' => 'Campinas', 'state' => 'SP', 'postal_code' => '13010000', 'street' => 'Av. Francisco Glicério', 'number' => '300'],
        ['city' => 'Belo Horizonte', 'state' => 'MG', 'postal_code' => '30130000', 'street' => 'Av. Afonso Pena', 'number' => '1000'],
        ['city' => 'Rio de Janeiro', 'state' => 'RJ', 'postal_code' => '20040002', 'street' => 'Av. Rio Branco', 'number' => '50'],
    ];

    public function run(): void
    {
        $workshopIndex = 0;
        Workshop::query()->orderBy('id')->each(function (Workshop $workshop) use (&$workshopIndex): void {
            if ($this->workshopHasAddress($workshop)) {
                return;
            }

            $demo = $this->workshopAddresses[$workshopIndex % count($this->workshopAddresses)];
            $workshopIndex++;

            $workshop->fill([
                'street' => $demo['street'],
                'number' => $demo['number'],
                'neighborhood' => $demo['neighborhood'],
                'city' => $demo['city'],
                'state' => $demo['state'],
                'cep' => $demo['cep'],
            ]);
            $workshop->save();
        });

        $userIndex = 0;
        User::query()
            ->where('user_type', 'user')
            ->orderBy('id')
            ->each(function (User $user) use (&$userIndex): void {
                if ($this->userHasCity($user)) {
                    return;
                }

                if (! $this->shouldFillUserDemoAddress($user)) {
                    return;
                }

                $demo = $this->userAddresses[$userIndex % count($this->userAddresses)];
                $userIndex++;

                if ($user->city === null || trim((string) $user->city) === '') {
                    $user->city = $demo['city'];
                }
                if ($user->state === null || trim((string) $user->state) === '') {
                    $user->state = $demo['state'];
                }
                if ($user->postal_code === null || trim((string) $user->postal_code) === '') {
                    $user->postal_code = $demo['postal_code'];
                }
                if ($user->street === null || trim((string) $user->street) === '') {
                    $user->street = $demo['street'];
                }
                if ($user->number === null || trim((string) $user->number) === '') {
                    $user->number = $demo['number'];
                }
                if ($user->country === null || trim((string) $user->country) === '') {
                    $user->country = 'BR';
                }

                $user->save();
            });
    }

    private function shouldFillUserDemoAddress(User $user): bool
    {
        if (str_ends_with(strtolower($user->email), '@vehicle-maintenance.test')) {
            return true;
        }

        if (strtolower($user->email) === 'fgoncalves2008@gmail.com') {
            return ! $this->userHasCity($user);
        }

        return ! $this->userHasCity($user);
    }

    private function workshopHasAddress(Workshop $workshop): bool
    {
        return filled($workshop->street) && filled($workshop->city);
    }

    private function userHasCity(User $user): bool
    {
        return filled($user->city);
    }
}
