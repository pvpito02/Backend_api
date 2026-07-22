<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Seeder;

class AnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = User::query()->where('email', 'admin@sandiara.sn')->value('id');

        Announcement::query()->updateOrCreate(
            ['title' => 'Réunion générale'],
            [
                'content' => 'Tous les agents sont conviés.',
                'when_label' => 'Vendredi · 09h00',
                'place' => 'Salle de conférence',
                'image_url' => null,
                'published_at' => now(),
                'starts_at' => now()->subDay(),
                'expires_at' => now()->addDays(7),
                'duration_hours' => 168,
                'is_active' => true,
                'priority' => 10,
                'created_by' => $adminId,
            ]
        );
    }
}
