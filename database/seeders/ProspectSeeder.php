<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\__Infrastructure__\Persistence\Eloquent\User;

class ProspectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Récupérer l'utilisateur test
        $testUser = User::where('email', 'test@test.com')->first();
        
        if (!$testUser) {
            $this->command->warn('Utilisateur test@test.com non trouvé. Exécutez UserSeeder d\'abord.');
            return;
        }

        // Créer des prospects de démonstration
        $prospects = [
            [
                'user_id' => $testUser->id,
                'name' => 'Restaurant Le Petit Bistro',
                'company' => 'Le Petit Bistro',
                'sector' => 'Restauration',
                'city' => 'Paris',
                'postal_code' => '75001',
                'address' => '12 Rue de la Paix, 75001 Paris',
                'contact_info' => json_encode([
                    'email' => 'contact@lepetitbistro.fr',
                    'phone' => '01 42 96 12 34',
                    'website' => 'www.lepetitbistro.fr'
                ]),
                'description' => 'Restaurant traditionnel français au cœur de Paris',
                'relevance_score' => 85,
                'status' => 'new',
                'source' => 'pages_jaunes',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'user_id' => $testUser->id,
                'name' => 'Boulangerie Martin',
                'company' => 'Boulangerie Martin',
                'sector' => 'Alimentation',
                'city' => 'Lyon',
                'postal_code' => '69001',
                'address' => '45 Rue des Terreaux, 69001 Lyon',
                'contact_info' => json_encode([
                    'email' => 'info@boulangerie-martin.fr',
                    'phone' => '04 78 27 33 45'
                ]),
                'description' => 'Boulangerie artisanale depuis 1987',
                'relevance_score' => 92,
                'status' => 'contacted',
                'source' => 'google_maps',
                'created_at' => now()->subDays(2),
                'updated_at' => now()
            ],
            [
                'user_id' => $testUser->id,
                'name' => 'Garage Dupont Auto',
                'company' => 'Dupont Auto Services',
                'sector' => 'Automobile',
                'city' => 'Marseille',
                'postal_code' => '13001',
                'address' => '78 Avenue de la République, 13001 Marseille',
                'contact_info' => json_encode([
                    'email' => 'contact@dupont-auto.com',
                    'phone' => '04 91 54 22 88',
                    'website' => 'www.dupont-auto.com'
                ]),
                'description' => 'Garage spécialisé en réparation toutes marques',
                'relevance_score' => 78,
                'status' => 'interested',
                'source' => 'pages_jaunes',
                'created_at' => now()->subWeek(),
                'updated_at' => now()->subDays(3)
            ],
            [
                'user_id' => $testUser->id,
                'name' => 'Cabinet Médical Dr. Leblanc',
                'company' => 'Cabinet Dr. Leblanc',
                'sector' => 'Santé',
                'city' => 'Toulouse',
                'postal_code' => '31000',
                'address' => '15 Place du Capitole, 31000 Toulouse',
                'contact_info' => json_encode([
                    'email' => 'secretariat@dr-leblanc.fr',
                    'phone' => '05 61 23 45 67'
                ]),
                'description' => 'Cabinet de médecine générale',
                'relevance_score' => 65,
                'status' => 'qualified',
                'source' => 'google_maps',
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDay()
            ],
            [
                'user_id' => $testUser->id,
                'name' => 'Librairie des Arts',
                'company' => 'Librairie des Arts',
                'sector' => 'Culture',
                'city' => 'Nice',
                'postal_code' => '06000',
                'address' => '23 Promenade des Anglais, 06000 Nice',
                'contact_info' => json_encode([
                    'email' => 'hello@librairie-arts.fr',
                    'phone' => '04 93 87 22 11',
                    'website' => 'www.librairie-arts.fr'
                ]),
                'description' => 'Librairie spécialisée en livres d\'art et culture',
                'relevance_score' => 88,
                'status' => 'new',
                'source' => 'manual',
                'created_at' => now()->subHours(6),
                'updated_at' => now()->subHours(6)
            ]
        ];

        foreach ($prospects as $prospect) {
            DB::table('prospects')->insert($prospect);
        }

        $this->command->info('Créé ' . count($prospects) . ' prospects de démonstration pour l\'utilisateur test@test.com');
    }
}