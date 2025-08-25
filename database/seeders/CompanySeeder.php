<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Location;
use App\Models\LocationProduct;
use App\Models\Product;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Pizza Palace company
        $pizzaPalace = Company::create([
            'name' => 'Pizza Palace'
        ]);

        // Create products for Pizza Palace
        $products = [
            ['name' => 'Margherita Pizza', 'base_prep_time_seconds' => 600], // 10 minutes
            ['name' => 'Pepperoni Pizza', 'base_prep_time_seconds' => 660], // 11 minutes
            ['name' => 'Caesar Salad', 'base_prep_time_seconds' => 300], // 5 minutes
            ['name' => 'Garlic Bread', 'base_prep_time_seconds' => 240], // 4 minutes
            ['name' => 'Pasta Carbonara', 'base_prep_time_seconds' => 900], // 15 minutes
        ];

        foreach ($products as $productData) {
            Product::create([
                'company_id' => $pizzaPalace->id,
                'name' => $productData['name'],
                'base_prep_time_seconds' => $productData['base_prep_time_seconds'],
            ]);
        }

        // Create locations for Pizza Palace
        $locations = [
            ['name' => 'Downtown Location', 'address' => '123 Main St, Downtown'],
            ['name' => 'Mall Location', 'address' => '456 Shopping Mall, Level 2'],
        ];

        foreach ($locations as $locationData) {
            $location = Location::create([
                'company_id' => $pizzaPalace->id,
                'name' => $locationData['name'],
                'address' => $locationData['address'],
            ]);

            // Add all products to each location
            foreach ($pizzaPalace->products as $product) {
                LocationProduct::create([
                    'location_id' => $location->id,
                    'product_id' => $product->id,
                    'is_available' => true,
                ]);
            }
        }

        // Create Burger Barn company
        $burgerBarn = Company::create([
            'name' => 'Burger Barn'
        ]);

        // Create products for Burger Barn
        $burgerProducts = [
            ['name' => 'Classic Burger', 'base_prep_time_seconds' => 480], // 8 minutes
            ['name' => 'Cheeseburger', 'base_prep_time_seconds' => 540], // 9 minutes
            ['name' => 'French Fries', 'base_prep_time_seconds' => 180], // 3 minutes
            ['name' => 'Chicken Wings', 'base_prep_time_seconds' => 720], // 12 minutes
        ];

        foreach ($burgerProducts as $productData) {
            Product::create([
                'company_id' => $burgerBarn->id,
                'name' => $productData['name'],
                'base_prep_time_seconds' => $productData['base_prep_time_seconds'],
            ]);
        }

        // Create one location for Burger Barn
        $burgerLocation = Location::create([
            'company_id' => $burgerBarn->id,
            'name' => 'Main Street Burger Barn',
            'address' => '789 Main Street',
        ]);

        // Add products to Burger Barn location
        foreach ($burgerBarn->products as $product) {
            LocationProduct::create([
                'location_id' => $burgerLocation->id,
                'product_id' => $product->id,
                'is_available' => true,
            ]);
        }
    }
}
