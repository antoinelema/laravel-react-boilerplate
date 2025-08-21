<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\__Infrastructure__\Persistence\Eloquent\User;

class ProspectNoteSeeder extends Seeder
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

        // Récupérer les prospects de l'utilisateur test
        $prospects = DB::table('prospects')
            ->where('user_id', $testUser->id)
            ->get();

        if ($prospects->isEmpty()) {
            $this->command->warn('Aucun prospect trouvé pour test@test.com. Exécutez ProspectSeeder d\'abord.');
            return;
        }

        // Créer des notes pour les prospects
        $notes = [
            [
                'prospect_id' => $prospects[0]->id,
                'user_id' => $testUser->id,
                'content' => 'Premier contact téléphonique. Le gérant semble intéressé par notre solution de gestion de commandes en ligne.',
                'type' => 'call',
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2)
            ],
            [
                'prospect_id' => $prospects[0]->id,
                'user_id' => $testUser->id,
                'content' => 'Email envoyé avec brochure détaillée et proposition de rendez-vous.',
                'type' => 'email',
                'created_at' => now()->subDay(),
                'updated_at' => now()->subDay()
            ],
            [
                'prospect_id' => $prospects[1]->id,
                'user_id' => $testUser->id,
                'content' => 'Visite du magasin effectuée. Bonne ambiance, équipe motivée. Demande de devis pour solution de caisse.',
                'type' => 'meeting',
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(3)
            ],
            [
                'prospect_id' => $prospects[1]->id,
                'user_id' => $testUser->id,
                'content' => 'À faire : Préparer devis personnalisé pour système de caisse avec module boulangerie.',
                'type' => 'task',
                'created_at' => now()->subHours(6),
                'updated_at' => now()->subHours(6)
            ],
            [
                'prospect_id' => $prospects[2]->id,
                'user_id' => $testUser->id,
                'content' => 'Garage en pleine expansion, besoin urgent d\'un logiciel de gestion d\'atelier. Très motivé.',
                'type' => 'note',
                'created_at' => now()->subWeek(),
                'updated_at' => now()->subWeek()
            ],
            [
                'prospect_id' => $prospects[2]->id,
                'user_id' => $testUser->id,
                'content' => 'Appel de suivi. Confirme l\'intérêt, souhaite une démonstration la semaine prochaine.',
                'type' => 'call',
                'created_at' => now()->subDays(4),
                'updated_at' => now()->subDays(4)
            ]
        ];

        // Ajouter des notes seulement si nous avons assez de prospects
        foreach ($notes as $note) {
            if ($note['prospect_id']) {
                DB::table('prospect_notes')->insert($note);
            }
        }

        $this->command->info('Créé ' . count($notes) . ' notes de démonstration pour les prospects de test@test.com');
    }
}