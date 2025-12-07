<?php

namespace Database\Factories;

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
        
        return [
            'license_plate' => strtoupper($this->faker->bothify('???####')),
            'renavam' => $this->faker->numerify('###########'),
            'brand' => $brand,
            'model' => $model,
            'year' => $this->faker->numberBetween(2010, date('Y')),
            'color' => $this->faker->randomElement($colors),
            'chassis' => $this->faker->optional()->bothify('?????????????????'),
            'engine' => $this->faker->optional()->bothify('?##?##?'),
        ];
    }
}
