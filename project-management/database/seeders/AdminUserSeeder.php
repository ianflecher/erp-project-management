<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminEmail = 'admin@example.com';
        $adminName = 'Admin User';
        $adminPassword = 'password123'; // change to a secure password

        // Check if admin already exists
        if (!User::where('email', $adminEmail)->exists()) {

            DB::transaction(function () use ($adminEmail, $adminName, $adminPassword) {

                $now = now();

                // Insert into hr_employees first
                $employeeId = DB::table('hr_employees')->insertGetId([
                    'full_name'  => $adminName,
                    'role'       => 'Admin',
                    'email'      => $adminEmail,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // Insert into users table
                $user = User::create([
                    'name'        => $adminName,
                    'email'       => $adminEmail,
                    'password'    => Hash::make($adminPassword),
                    'employee_id' => $employeeId,
                ]);
            });
        }
    }
}
