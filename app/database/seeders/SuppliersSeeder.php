<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SuppliersSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            ['name' => 'Giessegi',               'website' => 'https://www.giessegi.it',      'catalog_format' => 'mixed'],
            ['name' => 'Maronese',                'website' => 'https://www.maronese.it',      'catalog_format' => 'mixed'],
            ['name' => 'Colombini',               'website' => 'https://www.colombini.it',     'catalog_format' => 'pdf'],
            ['name' => 'Aran Cucine',             'website' => 'https://www.arancucine.it',    'catalog_format' => 'web'],
            ['name' => 'Evo Cucine',              'website' => 'https://www.evocucine.it',     'catalog_format' => 'web'],
            ['name' => 'SB Design Solution',      'website' => null,                           'catalog_format' => 'pdf'],
            ['name' => 'Nicoletti Home',          'website' => 'https://www.nicolettihome.it', 'catalog_format' => 'pdf'],
            ['name' => 'Cuborosso',               'website' => null,                           'catalog_format' => 'mixed'],
            ['name' => 'Complementi Taglio 60',   'website' => null,                           'catalog_format' => 'mixed'],
            ['name' => 'La Primavera',            'website' => null,                           'catalog_format' => 'mixed'],
            ['name' => 'Gaber',                   'website' => 'https://www.gaber.it',         'catalog_format' => 'pdf'],
            ['name' => 'Bizzotto',                'website' => 'https://www.bizzotto.com',     'catalog_format' => 'web'],
        ];

        foreach ($suppliers as $data) {
            Supplier::firstOrCreate(
                ['name' => $data['name']],
                array_merge($data, ['markup_default' => 1.35, 'is_active' => true])
            );
        }
    }
}
