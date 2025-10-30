<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class InventoriesTableSeeder extends Seeder
{
    public function run()
    {
        $now = Carbon::now();

        // 1️⃣ Insert a dummy user and get the ID
        $userId = DB::table('users')->insertGetId([
            'name' => 'Seeder User',
            'email' => 'seeder3234@example.com',
            'password' => Hash::make('password'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 2️⃣ Insert inventories including labor
        DB::table('inventories')->insert([
            [
                'sku' => 40001434,
                'name' => 'Laptop Dell XPS 13',
                'description' => 'High-performance laptop for office work',
                'quantity' => 10,
                'expiration_date' => null,
                'category' => 'Electronics',
                'warehouse' => 'Main Warehouse',
                'zone' => 'A1',
                'user_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'sku' => 4000234,
                'name' => 'HP LaserJet Printer',
                'description' => 'Office printer for documents',
                'quantity' => 5,
                'expiration_date' => null,
                'category' => 'Electronics',
                'warehouse' => 'Main Warehouse',
                'zone' => 'B2',
                'user_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'sku' => 4000334,
                'name' => 'Office Chair',
                'description' => 'Ergonomic chair for office use',
                'quantity' => 20,
                'expiration_date' => null,
                'category' => 'Furniture',
                'warehouse' => 'Secondary Warehouse',
                'zone' => 'C3',
                'user_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'sku' => 4000434,
                'name' => 'Whiteboard Marker',
                'description' => 'Set of 12 markers',
                'quantity' => 50,
                'expiration_date' => null,
                'category' => 'Stationery',
                'warehouse' => 'Main Warehouse',
                'zone' => 'D4',
                'user_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // ✅ LABOR entries inside inventories
            [
                'sku' => 40001344,
                'name' => 'Carpenter',
                'description' => 'Skilled worker for carpentry',
                'quantity' => 0,
                'expiration_date' => null,
                'category' => 'Labor',
                'warehouse' => 'Manpower',
                'zone' => 'L1',
                'user_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'sku' => 40002344,
                'name' => 'Electrician',
                'description' => 'Electric work specialist',
                'quantity' => 0,
                'expiration_date' => null,
                'category' => 'Labor',
                'warehouse' => 'Manpower',
                'zone' => 'L2',
                'user_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'sku' => 40003344,
                'name' => 'Mason',
                'description' => 'Construction mason',
                'quantity' => 0,
                'expiration_date' => null,
                'category' => 'Labor',
                'warehouse' => 'Manpower',
                'zone' => 'L3',
                'user_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
