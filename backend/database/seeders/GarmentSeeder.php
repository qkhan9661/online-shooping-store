<?php

namespace Database\Seeders;

use App\Models\Garment;
use Illuminate\Database\Seeder;

class GarmentSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'sku' => 'TEE-001',
                'name' => 'Essential Cotton Tee',
                'description' => 'Relaxed everyday tee with soft hand-feel.',
                'price' => 29.99,
                'image_path' => null,
                'size_chart' => [
                    'XS' => ['chest' => 82, 'waist' => 66, 'hips' => 88, 'shoulder' => 38],
                    'S' => ['chest' => 88, 'waist' => 72, 'hips' => 94, 'shoulder' => 40],
                    'M' => ['chest' => 94, 'waist' => 78, 'hips' => 100, 'shoulder' => 42],
                    'L' => ['chest' => 102, 'waist' => 86, 'hips' => 108, 'shoulder' => 44],
                    'XL' => ['chest' => 110, 'waist' => 94, 'hips' => 116, 'shoulder' => 46],
                ],
            ],
            [
                'sku' => 'HOOD-014',
                'name' => 'City Zip Hoodie',
                'description' => 'Mid-weight fleece hoodie with modern fit.',
                'price' => 79.50,
                'image_path' => null,
                'size_chart' => [
                    'S' => ['chest' => 90, 'waist' => 74, 'hips' => 92, 'shoulder' => 41],
                    'M' => ['chest' => 96, 'waist' => 80, 'hips' => 98, 'shoulder' => 43],
                    'L' => ['chest' => 104, 'waist' => 88, 'hips' => 106, 'shoulder' => 45],
                    'XL' => ['chest' => 112, 'waist' => 96, 'hips' => 114, 'shoulder' => 47],
                ],
            ],
            [
                'sku' => 'JKT-220',
                'name' => 'Lightweight Jacket',
                'description' => 'Packable shell for transitional weather.',
                'price' => 119.00,
                'image_path' => null,
                'size_chart' => [
                    'S' => ['chest' => 92, 'waist' => 76, 'hips' => 94, 'shoulder' => 42],
                    'M' => ['chest' => 98, 'waist' => 82, 'hips' => 100, 'shoulder' => 44],
                    'L' => ['chest' => 106, 'waist' => 90, 'hips' => 108, 'shoulder' => 46],
                    'XL' => ['chest' => 114, 'waist' => 98, 'hips' => 116, 'shoulder' => 48],
                ],
            ],
        ];

        foreach ($items as $row) {
            Garment::query()->updateOrCreate(['sku' => $row['sku']], $row);
        }
    }
}
