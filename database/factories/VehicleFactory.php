<?php

namespace Database\Factories;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $brands = ['Toyota', 'Honda', 'Volkswagen', 'Ford', 'Chevrolet', 'Fiat', 'Renault', 'Hyundai'];
        $models = ['Corolla', 'Civic', 'Gol', 'Ka', 'Onix', 'Uno', 'Sandero', 'HB20'];
        $colors = ['Branco', 'Preto', 'Prata', 'Vermelho', 'Azul', 'Cinza'];

        $brand = $this->faker->randomElement($brands);
        $model = $this->faker->randomElement($models);
        $kilometers = $this->faker->numberBetween(10000, 180000);

        return [
            'license_plate' => strtoupper($this->faker->bothify('???#?##')),
            'renavam' => $this->faker->unique()->numerify('###########'),
            'brand' => $brand,
            'model' => $model,
            'year' => $this->faker->numberBetween(2010, date('Y')),
            'color' => $this->faker->randomElement($colors),
            'chassis' => $this->uniqueVin(),
            'motorization' => $this->faker->optional()->randomElement(['1.0', '1.4', '1.6', '1.6 Turbo', '2.0 Turbo']),
            'engine' => $this->faker->optional()->bothify('?##?##?########'),
            'current_kilometers' => $kilometers,
            'odometer_at_registration' => $kilometers,
        ];
    }

    private function uniqueVin(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPRSTUVWXYZ0123456789';

        do {
            $vin = '';
            for ($i = 0; $i < 17; $i++) {
                $vin .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (Vehicle::query()->where('chassis', $vin)->exists());

        return $vin;
    }
}
