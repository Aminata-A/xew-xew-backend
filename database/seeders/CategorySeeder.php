<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'label' => 'Concerts',
                'description' => 'Événements musicaux et concerts',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'label' => 'Festivals',
                'description' => 'Festivals culturels et artistiques',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'label' => 'Conférences',
                'description' => 'Conférences et séminaires professionnels',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'label' => 'Sports',
                'description' => 'Événements sportifs et compétitions',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'label' => 'Arts',
                'description' => 'Expositions et événements artistiques',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'label' => 'Expositions',
                'description' => 'Expositions d\'art et de culture',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'label' => 'Ateliers',
                'description' => 'Ateliers et formations',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'label' => 'Soirées',
                'description' => 'Soirées et événements festifs',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'label' => 'Théâtre',
                'description' => 'Pièces de théâtre et spectacles',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'label' => 'Cinéma',
                'description' => 'Projections et événements cinématographiques',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'label' => 'Gastronomie',
                'description' => 'Événements culinaires et dégustations',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ];

        DB::table('categories')->insert($categories);
    }
}
