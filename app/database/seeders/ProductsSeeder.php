<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class ProductsSeeder extends Seeder
{
    public function run(): void
    {
        // Lookup helpers
        $s = fn(string $name) => Supplier::where('name', $name)->value('id');
        $c = fn(string $name) => Category::where('name', 'like', "%$name%")->value('id');

        $products = [

            // ── MARONESE (cucine) ────────────────────────────────────────────
            [
                'supplier' => 'Maronese',
                'category' => 'Cucine',
                'name'        => 'Cucina Seta — Composizione lineare',
                'sku'         => 'MAR-SETA-L01',
                'collection'  => 'Seta',
                'description' => 'Cucina componibile in stile contemporaneo con ante in finitura laccata opaca. Linee pulite, maniglia integrata fresata.',
                'materials'   => ['anta' => 'MDF laccato opaco', 'struttura' => 'truciolare FSC CARB2', 'top' => 'laminato HPL 12mm'],
                'finishes'    => ['Laccato opaco', 'Laccato lucido', 'Melaminico'],
                'colors'      => ['Bianco Assoluto', 'Grigio Cenere', 'Antracite', 'Blu Notte'],
                'price_list'  => 5200.00,
                'cost'        => 3100.00,
                'is_featured' => true,
            ],
            [
                'supplier' => 'Maronese',
                'category' => 'Cucine',
                'name'        => 'Cucina Brio — Composizione ad angolo',
                'sku'         => 'MAR-BRIO-A01',
                'collection'  => 'Brio',
                'description' => 'Cucina ad angolo con pensili aperti e boiserie. Finitura impiallacciata in rovere con dettagli metallici.',
                'materials'   => ['anta' => 'impiallacciato rovere', 'struttura' => 'truciolare FSC', 'top' => 'quarzo 20mm'],
                'finishes'    => ['Impiallacciato naturale', 'Impiallacciato tinto', 'Laccato opaco'],
                'colors'      => ['Rovere Tabacco', 'Rovere Naturale', 'Bianco Perla'],
                'price_list'  => 7800.00,
                'cost'        => 4600.00,
                'is_featured' => false,
            ],

            // ── ARAN CUCINE ──────────────────────────────────────────────────
            [
                'supplier' => 'Aran Cucine',
                'category' => 'Cucine',
                'name'        => 'Cucina Lab 13 — Island',
                'sku'         => 'ARA-LAB13-IS',
                'collection'  => 'Lab 13',
                'description' => 'Cucina con isola centrale in stile industrial-chic. Struttura in metallo verniciato, ante in PET riciclato.',
                'materials'   => ['anta' => 'PET riciclato 19mm', 'struttura' => 'metallo verniciato', 'top_isola' => 'acciaio inox spazzolato'],
                'finishes'    => ['PET riciclato', 'Metallizzato', 'Laccato opaco'],
                'colors'      => ['Titanio', 'Sabbia', 'Verde Bosco', 'Terracotta'],
                'price_list'  => 9400.00,
                'cost'        => 5600.00,
                'is_featured' => true,
            ],
            [
                'supplier' => 'Aran Cucine',
                'category' => 'Cucine',
                'name'        => 'Cucina Cloe — Composizione lineare',
                'sku'         => 'ARA-CLOE-L01',
                'collection'  => 'Cloe',
                'description' => 'Cucina classica contemporanea con ante a telaio. Disponibile in laccato e melaminico.',
                'materials'   => ['anta' => 'MDF telaio', 'struttura' => 'truciolare', 'top' => 'granito o laminato'],
                'finishes'    => ['Laccato opaco', 'Melaminico'],
                'colors'      => ['Bianco', 'Grigio Tortora', 'Panna'],
                'price_list'  => 4300.00,
                'cost'        => null,
            ],

            // ── EVO CUCINE ───────────────────────────────────────────────────
            [
                'supplier' => 'Evo Cucine',
                'category' => 'Cucine',
                'name'        => 'Cucina Bali — Composizione con penisola',
                'sku'         => 'EVO-BALI-P01',
                'collection'  => 'Bali',
                'description' => 'Cucina colorata con penisola, caratterizzata da ante laccate in colori vivaci. Ideale per ambienti open space.',
                'materials'   => ['anta' => 'MDF laccato', 'struttura' => 'truciolare', 'piano' => 'laminato'],
                'finishes'    => ['Laccato opaco', 'Laccato lucido'],
                'colors'      => ['Giallo Senape', 'Verde Salvia', 'Rosso Ciliegia', 'Blu Cobalto', 'Bianco'],
                'price_list'  => 5800.00,
                'is_featured' => false,
            ],

            // ── GIESSEGI (ufficio / home office) ────────────────────────────
            [
                'supplier' => 'Giessegi',
                'category' => 'Living',
                'name'        => 'Scrivania Office Time 160×80',
                'sku'         => 'OF|100-160',
                'collection'  => 'Office Time',
                'description' => 'Scrivania direzionale con struttura in acciaio verniciato e piano in melaminico. Passacavi integrato.',
                'materials'   => ['piano' => 'melaminico 25mm', 'struttura' => 'acciaio verniciato'],
                'finishes'    => ['Melaminico', 'Laccato'],
                'colors'      => ['Wengé', 'Rovere Bianco', 'Frassino', 'Antracite'],
                'price_list'  => 890.00,
                'cost'        => 520.00,
                'dimensions'  => ['l' => 160, 'p' => 80, 'h' => 74],
            ],
            [
                'supplier' => 'Giessegi',
                'category' => 'Living',
                'name'        => 'Libreria Opera — Modulo base',
                'sku'         => 'OF|250-M01',
                'collection'  => 'Opera',
                'description' => 'Sistema libreria modulare con ante scorrevoli in vetro. Combinabile in larghezza e altezza.',
                'materials'   => ['struttura' => 'melaminico', 'ante' => 'vetro temperato 6mm', 'ripiani' => 'melaminico 25mm'],
                'finishes'    => ['Melaminico', 'Laccato', 'Vetro'],
                'colors'      => ['Bianco', 'Grigio', 'Rovere Miele'],
                'price_list'  => 1240.00,
                'cost'        => 720.00,
                'dimensions'  => ['l' => 120, 'p' => 40, 'h' => 200],
                'is_featured' => true,
            ],

            // ── NICOLETTI HOME (divani) ──────────────────────────────────────
            [
                'supplier' => 'Nicoletti Home',
                'category' => 'Salotti',
                'name'        => 'Divano Chester 3 posti',
                'collection'  => 'Chester',
                'description' => 'Divano Chesterfield con capitonné, struttura in legno massello, imbottitura in piuma d\'oca.',
                'materials'   => ['struttura' => 'legno massello faggio', 'imbottitura' => 'piuma d\'oca + memory foam', 'rivestimento' => 'pelle naturale'],
                'finishes'    => ['Pelle naturale', 'Ecopelle', 'Tessuto bouclé'],
                'colors'      => ['Marrone Cognac', 'Nero', 'Verde Bottiglia', 'Crema'],
                'price_list'  => 3200.00,
                'is_active'   => true,
                'is_available' => false, // disponibile su ordinazione
                'notes'       => 'Disponibile su ordinazione — 8-10 settimane di consegna',
            ],
            [
                'supplier' => 'Nicoletti Home',
                'category' => 'Salotti',
                'name'        => 'Divano Reef — Componibile L',
                'collection'  => 'Reef',
                'description' => 'Divano componibile angolare con chaise longue. Struttura leggera con piedini in metallo dorato.',
                'materials'   => ['struttura' => 'poliuretano espanso HD', 'rivestimento' => 'tessuto tecnico removibile'],
                'finishes'    => ['Tessuto tecnico', 'Velluto', 'Pelle'],
                'colors'      => ['Grigio Chiaro', 'Beige', 'Ottanio', 'Taupe'],
                'price_list'  => 4100.00,
                'is_featured' => true,
            ],

            // ── COLOMBINI (camere da letto) ──────────────────────────────────
            [
                'supplier' => 'Colombini',
                'category' => 'Camera',
                'name'        => 'Camera Vela — Composizione completa',
                'collection'  => 'Vela',
                'description' => 'Camera da letto con testiera imbottita, armadio scorrevole 4 ante e cassettiera abbinata.',
                'materials'   => ['struttura' => 'truciolare FSC', 'ante_armadio' => 'specchio o laccato opaco', 'testiera' => 'tessuto cat. A'],
                'finishes'    => ['Laccato opaco', 'Specchiato', 'Melaminico'],
                'colors'      => ['Bianco Ghiaccio', 'Tortora', 'Antracite'],
                'price_list'  => 6400.00,
            ],
            [
                'supplier' => 'Colombini',
                'category' => 'Camera',
                'name'        => 'Armadio Sestante — 6 ante scorrevoli',
                'sku'         => 'COL-SEST-6A',
                'collection'  => 'Sestante',
                'description' => 'Armadio con 6 ante scorrevoli, interno su misura con cassetti e vani appendiabiti multipli.',
                'materials'   => ['struttura' => 'truciolare 18mm', 'ante' => 'vetro satinato o specchio', 'interni' => 'melaminico'],
                'finishes'    => ['Specchiato', 'Vetro satinato', 'Laccato'],
                'colors'      => ['Grafite', 'Bianco', 'Bronzo'],
                'price_list'  => 3800.00,
                'dimensions'  => ['l' => 300, 'p' => 65, 'h' => 240],
            ],

            // ── GABER (design / sedia) ───────────────────────────────────────
            [
                'supplier' => 'Gaber',
                'category' => 'Complementi',
                'name'        => 'Sedia Manaa con braccioli',
                'sku'         => 'MANAA-TP',
                'collection'  => 'Manaa',
                'brand'       => 'Gaber',
                'description' => 'Sedia contemporanea con scocca in polipropilene e base in metallo. Design premiato. Impilabile.',
                'materials'   => ['scocca' => 'polipropilene riciclato', 'base' => 'acciaio verniciato', 'imbottitura' => 'poliuretano'],
                'finishes'    => ['Polipropilene', 'Imbottita tessuto', 'Imbottita pelle'],
                'colors'      => ['Bianco', 'Nero', 'Grigio', 'Verde', 'Rosso'],
                'price_list'  => 340.00,
                'cost'        => 195.00,
                'is_featured' => true,
            ],
            [
                'supplier' => 'Gaber',
                'category' => 'Complementi',
                'name'        => 'Tavolo Aky — Piano rotondo Ø120',
                'sku'         => 'AKY-R120',
                'collection'  => 'Aky',
                'brand'       => 'Gaber',
                'description' => 'Tavolo rotondo con piano in MDF laccato e base centrale in metallo. Disponibile fisso o allungabile.',
                'materials'   => ['piano' => 'MDF laccato 30mm', 'base' => 'acciaio verniciato'],
                'finishes'    => ['Laccato opaco', 'Laccato lucido'],
                'colors'      => ['Bianco', 'Nero', 'Cemento', 'Marrone'],
                'price_list'  => 1280.00,
                'cost'        => 740.00,
                'dimensions'  => ['l' => 120, 'p' => 120, 'h' => 75],
            ],

            // ── LA PRIMAVERA (camerette) ──────────────────────────────────────
            [
                'supplier' => 'La Primavera',
                'category' => 'Camerette',
                'name'        => 'Cameretta Aurora — Set completo',
                'collection'  => 'Aurora',
                'description' => 'Cameretta completa per bambini 3-12 anni: letto a castello, scrivania, armadio e libreria abbinati.',
                'materials'   => ['struttura' => 'truciolare FSC CARB2', 'bordi' => 'ABS antischeggia'],
                'finishes'    => ['Melaminico', 'Laccato opaco'],
                'colors'      => ['Rosa Confetto', 'Azzurro Cielo', 'Bianco', 'Verde Menta'],
                'price_list'  => 2800.00,
            ],

            // ── BOZZE da web (is_active=false, senza prezzi) ─────────────────
            [
                'supplier' => 'Maronese',
                'category' => 'Cucine',
                'name'        => 'Cucina Matrix — Composizione (bozza)',
                'collection'  => 'Matrix',
                'description' => 'Rilevato dal sito Maronese. Cucina con maniglia integrata e top ultracompatto.',
                'source_url'  => 'https://www.maronese.it/cucine/matrix',
                'is_active'   => false,
                'is_available' => false,
                'notes'       => 'Importato via web analysis — completare con listino prezzi',
            ],
            [
                'supplier' => 'Evo Cucine',
                'category' => 'Cucine',
                'name'        => 'Cucina Smile — Versione Laccato (bozza)',
                'collection'  => 'Smile',
                'description' => 'Rilevato dal sito Evo Cucine. Cucina colorata a basso costo con ante laccate.',
                'source_url'  => 'https://www.evocucine.it/smile',
                'is_active'   => false,
                'is_available' => false,
                'notes'       => 'Importato via web analysis — completare con listino prezzi',
            ],
        ];

        foreach ($products as $data) {
            $supplierName = $data['supplier'];
            $categoryHint = $data['category'] ?? null;

            unset($data['supplier'], $data['category']);

            $supplierId = $s($supplierName);
            if (! $supplierId) {
                continue; // skip if seeder order is wrong
            }

            $categoryId = $categoryHint ? $c($categoryHint) : null;

            Product::create(array_merge([
                'supplier_id'  => $supplierId,
                'category_id'  => $categoryId,
                'is_active'    => true,
                'is_available' => true,
                'is_featured'  => false,
            ], $data));
        }
    }
}
