<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pages = [
            [
                'title' => 'Termos de Uso',
                'slug' => 'termos-de-uso',
                'content' => '<h1>Termos de Uso</h1><p>Conteúdo inicial dos termos...</p>',
                'meta_description' => 'Termos e condições de uso da plataforma.'
            ],
            [
                'title' => 'Política de Privacidade',
                'slug' => 'politica-de-privacidade',
                'content' => '<h1>Política de Privacidade</h1><p>Como cuidamos dos seus dados...</p>',
                'meta_description' => 'Nossa política de proteção de dados.'
            ],
            [
                'title' => 'Cookies',
                'slug' => 'cookies',
                'content' => '<h1>Cookies</h1><p>Informações sobre cookies utilizados no site...</p>',
                'meta_description' => 'Política de cookies da plataforma.'
            ],
        ];

        foreach ($pages as $page) {
            \App\Models\Page::updateOrCreate(['slug' => $page['slug']], $page);
        }
    }
}
