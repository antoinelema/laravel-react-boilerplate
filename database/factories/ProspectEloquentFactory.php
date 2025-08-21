<?php

namespace Database\Factories;

use App\__Infrastructure__\Eloquent\ProspectEloquent;
use App\__Infrastructure__\Eloquent\UserEloquent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory Laravel pour ProspectEloquent (nécessaire pour les tests)
 * Note: Utiliser la factory du domaine (App\__Domain__\Data\Prospect\Factory) pour la logique métier
 */
class ProspectEloquentFactory extends Factory
{
    protected $model = ProspectEloquent::class;

    public function definition(): array
    {
        $sectors = ['restaurant', 'technology', 'healthcare', 'retail', 'finance', 'education'];
        $statuses = ['new', 'contacted', 'interested', 'qualified', 'converted', 'rejected'];
        $sources = ['google_maps', 'pages_jaunes', 'manual'];

        return [
            'user_id' => UserEloquent::factory(),
            'name' => $this->faker->company(),
            'company' => $this->faker->optional(0.8)->company(),
            'sector' => $this->faker->randomElement($sectors),
            'city' => $this->faker->city(),
            'postal_code' => $this->faker->postcode(),
            'address' => $this->faker->optional(0.7)->address(),
            'contact_info' => function () {
                $info = [];
                
                if ($this->faker->boolean(70)) {
                    $info['phone'] = $this->faker->phoneNumber();
                }
                
                if ($this->faker->boolean(50)) {
                    $info['email'] = $this->faker->companyEmail();
                }
                
                if ($this->faker->boolean(40)) {
                    $info['website'] = 'https://' . $this->faker->domainName();
                }

                return $info;
            },
            'description' => $this->faker->optional(0.6)->sentence(10),
            'relevance_score' => $this->faker->numberBetween(0, 100),
            'status' => $this->faker->randomElement($statuses),
            'source' => $this->faker->randomElement($sources),
            'external_id' => $this->faker->optional(0.8)->bothify('ext_###???'),
            'raw_data' => function () {
                return $this->faker->boolean(60) ? [
                    'api_response' => ['status' => 'ok', 'timestamp' => now()->toISOString()],
                    'metadata' => ['version' => '1.0']
                ] : [];
            },
        ];
    }

    /**
     * Prospect avec score élevé
     */
    public function highScore(): static
    {
        return $this->state(fn (array $attributes) => [
            'relevance_score' => $this->faker->numberBetween(80, 100),
            'contact_info' => [
                'email' => $this->faker->companyEmail(),
                'phone' => $this->faker->phoneNumber(),
                'website' => 'https://' . $this->faker->domainName(),
            ],
        ]);
    }

    /**
     * Prospect dans un secteur spécifique
     */
    public function inSector(string $sector): static
    {
        return $this->state(fn (array $attributes) => [
            'sector' => $sector,
        ]);
    }

    /**
     * Prospect dans une ville spécifique
     */
    public function inCity(string $city): static
    {
        return $this->state(fn (array $attributes) => [
            'city' => $city,
        ]);
    }

    /**
     * Prospect avec un statut spécifique
     */
    public function withStatus(string $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }

    /**
     * Prospect provenant de Google Maps
     */
    public function fromGoogleMaps(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'google_maps',
            'external_id' => 'place_' . $this->faker->bothify('###???'),
            'raw_data' => [
                'place_id' => 'ChIJ' . $this->faker->bothify('??##??##??##'),
                'rating' => $this->faker->randomFloat(1, 1, 5),
                'user_ratings_total' => $this->faker->numberBetween(1, 500),
            ],
        ]);
    }

    /**
     * Prospect provenant des Pages Jaunes
     */
    public function fromPagesJaunes(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'pages_jaunes',
            'external_id' => 'pj_' . $this->faker->bothify('####??'),
            'contact_info' => [
                'email' => $this->faker->companyEmail(),
                'phone' => $this->faker->phoneNumber(),
            ],
        ]);
    }

    /**
     * Prospect créé manuellement
     */
    public function manual(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'manual',
            'external_id' => null,
            'raw_data' => [],
        ]);
    }

    /**
     * Prospect avec toutes les informations de contact
     */
    public function withFullContactInfo(): static
    {
        return $this->state(fn (array $attributes) => [
            'contact_info' => [
                'email' => $this->faker->companyEmail(),
                'phone' => $this->faker->phoneNumber(),
                'website' => 'https://' . $this->faker->domainName(),
                'social_networks' => [
                    'linkedin' => 'https://linkedin.com/company/' . $this->faker->slug(),
                    'twitter' => '@' . $this->faker->userName(),
                ],
            ],
        ]);
    }

    /**
     * Prospect sans informations de contact
     */
    public function withoutContactInfo(): static
    {
        return $this->state(fn (array $attributes) => [
            'contact_info' => [],
        ]);
    }
}