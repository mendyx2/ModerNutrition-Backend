<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Seed the initial product catalog across the 4 primary categories:
     * - Snacks
     * - Cereals
     * - Swallows
     * - Beverages
     */
    public function run(): void
    {
        $products = [
            [
                'sku'                 => 'VITA-ACT-001',
                'name'                => 'VitaActive™ Complete Meal Cereal',
                'description'         => 'Rich in essential micro-nutrients, plant proteins, and digestive enzymes. Formulated to sustain high energy, promote lean muscle, and support holistic daily vitality.',
                'category'            => 'Cereals',
                'currency'            => 'USD',
                'price_cents'         => 4500,
                'pv'                  => 35.00,
                'cv'                  => 30.00,
                'available_countries' => ['COD'],
                'status'              => 'active',
                'image_path'          => 'assets/products/protein-mix.png',
            ],
            [
                'sku'                 => 'VITA-GLD-002',
                'name'                => 'VitaGold™ Fortified Swallow Mix',
                'description'         => 'Engineered to blend effortlessly with Fufu, Chikwangue, and traditional starch swallows. Enriched with Iron, Zinc, Vitamin A, and Essential B-Complex.',
                'category'            => 'Swallows',
                'currency'            => 'USD',
                'price_cents'         => 3800,
                'pv'                  => 28.00,
                'cv'                  => 25.00,
                'available_countries' => ['COD'],
                'status'              => 'active',
                'image_path'          => 'assets/products/family-nutrition.png',
            ],
            [
                'sku'                 => 'WELL-GRN-003',
                'name'                => 'Daily Greens Vitality Elixir',
                'description'         => 'Organic Moringa, Spirulina, and Baobab extract for daily cellular rejuvenation and natural vitality. Refreshing botanical blend.',
                'category'            => 'Beverages',
                'currency'            => 'USD',
                'price_cents'         => 3000,
                'pv'                  => 22.00,
                'cv'                  => 20.00,
                'available_countries' => ['COD'],
                'status'              => 'active',
                'image_path'          => 'assets/products/daily-greens.png',
            ],
            [
                'sku'                 => 'SNK-CRN-004',
                'name'                => 'VitaCrunch™ Nutri-Bites',
                'description'         => 'Delicious crunchy roasted soy and grain clusters fortified with Zinc, B-Vitamins, and healthy prebiotic fiber. Perfect guilt-free nutrition on the go.',
                'category'            => 'Snacks',
                'currency'            => 'USD',
                'price_cents'         => 2500,
                'pv'                  => 18.00,
                'cv'                  => 15.00,
                'available_countries' => ['COD'],
                'status'              => 'active',
                'image_path'          => 'assets/products/vitamin-pack.png',
            ],
        ];

        foreach ($products as $data) {
            Product::updateOrCreate(
                ['sku' => $data['sku']],
                $data
            );
        }
    }
}
