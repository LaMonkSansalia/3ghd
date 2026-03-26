<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShowroomController extends Controller
{
    public function categories(): JsonResponse
    {
        $categories = Category::withCount([
            'products' => fn ($q) => $q->where('is_active', true),
        ])
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            // PHP-side filter: HAVING non funziona su colonne virtuali in SQLite (env test)
            ->filter(fn ($c) => $c->products_count > 0)
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'product_count' => $c->products_count,
            ])
            ->values();

        return response()->json($categories);
    }

    public function products(Request $request): JsonResponse
    {
        $products = Product::with([
            'supplier:id,name,markup_default',
            'category:id,name,slug',
            'media',
        ])
            ->where('is_active', true)
            ->when(
                $request->query('category'),
                fn ($q, $slug) => $q->whereHas('category', fn ($q) => $q->where('slug', $slug))
            )
            ->take(200)
            ->get()
            ->map(fn ($p) => $this->mapProductCard($p));

        return response()->json(['data' => $products]);
    }

    public function product(string $code): JsonResponse
    {
        $p = Product::with([
            'supplier:id,name,markup_default',
            'category:id,name,slug',
            'media',
        ])
            ->where('product_code', $code)
            ->where('is_active', true)
            ->firstOrFail();

        return response()->json($this->mapProductDetail($p));
    }

    private function mapProductCard(Product $p): array
    {
        return [
            'product_code' => $p->product_code,
            'name' => $p->name,
            'brand' => $p->brand,
            'collection' => $p->collection,
            'supplier' => $p->supplier?->name,
            'category' => $p->category?->name,
            'price' => $p->cost !== null
                ? round((float) $p->cost * $p->effectiveMarkup(), 2)
                : null,
            'tipo_prodotto' => $p->tipo_prodotto,
            'thumb' => $p->getFirstMediaUrl('images', 'thumb') ?: null,
            'card' => $p->getFirstMediaUrl('images', 'card') ?: null,
            'is_featured' => $p->is_featured,
        ];
    }

    private function mapProductDetail(Product $p): array
    {
        return array_merge($this->mapProductCard($p), [
            'description' => $p->description,
            'materials' => $p->materials ?? [],
            'dimensions' => $p->dimensions ?? [],
            'colors' => $p->colors ?? [],
            'finishes' => $p->finishes ?? [],
            'category' => [
                'name' => $p->category?->name,
                'slug' => $p->category?->slug,
            ],
            'images' => $p->getMedia('images')->map(fn ($m) => [
                'thumb' => $m->getUrl('thumb'),
                'card' => $m->getUrl('card'),
            ])->toArray(),
        ]);
    }
}
