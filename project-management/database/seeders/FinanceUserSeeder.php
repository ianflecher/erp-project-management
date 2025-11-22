<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class FinanceUserSeeder extends Seeder
{
    public function run(): void
    {
        $financeEmail = 'finance@example.com';
        $financeName = 'Finance User';
        $financePassword = 'password123'; // change this to a secure password

        // Check if finance user already exists
        if (!User::where('email', $financeEmail)->exists()) {

            DB::transaction(function () use ($financeEmail, $financeName, $financePassword) {

                $now = now();

                // Insert into hr_employees first
                $employeeId = DB::table('hr_employees')->insertGetId([
                    'full_name'  => $financeName,
                    'role'       => 'Finance',
                    'email'      => $financeEmail,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // Insert into users table
                $user = User::create([
                    'name'        => $financeName,
                    'email'       => $financeEmail,
                    'password'    => Hash::make($financePassword),
                    'employee_id' => $employeeId,
                ]);
            });
        }
    }
}
