<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Camera da Letto', 'sort_order' => 1],
            ['name' => 'Cucine',          'sort_order' => 2],
            ['name' => 'Living Room',     'sort_order' => 3],
            ['name' => 'Salotti e Divani','sort_order' => 4],
            ['name' => 'Camerette',       'sort_order' => 5],
            ['name' => 'Complementi, Tavoli e Sedie', 'sort_order' => 6],
            ['name' => 'Catalogo-W',      'sort_order' => 7],
        ];

        foreach ($categories as $data) {
            Category::firstOrCreate(
                ['slug' => Str::slug($data['name'])],
                $data
            );
        }
    }
}
