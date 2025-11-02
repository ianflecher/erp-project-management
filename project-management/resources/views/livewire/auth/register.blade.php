<?php

namespace App\Http\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.landingapp')] class extends Component {
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $role = '';
    public int $step = 1;

    // Step 1: validate name & email
    public function nextStep(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
        ]);
        $this->step = 2;
    }

    // Step 2: go back
    public function prevStep(): void
    {
        $this->step = 1;
    }

    // Register user
    public function register(): void
    {
        $this->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string'],
        ]);

        DB::transaction(function () {
            $user = User::create([
                'name' => $this->name,
                'email' => $this->email,
                'password' => Hash::make($this->password),
            ]);

            $employeeId = DB::table('hr_employees')->insertGetId([
                'full_name'  => $this->name,
                'role'       => $this->role,
                'email'      => $this->email,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $user->employee_id = $employeeId;
            $user->save();
        });

        Session::flash('status', 'Registration successful. Please log in.');
        $this->redirect(route('login')); // make sure this route exists
    }
};
?>
<div class="flex justify-center items-center min-h-screen bg-gray-100 p-4">

    <div class="w-full max-w-md bg-[#124116]/95 backdrop-blur-lg shadow-2xl rounded-2xl p-8 border border-[#1A5A20] text-white">

        <h2 class="text-2xl font-bold mb-2">Create an account</h2>
        <p class="text-green-200 mb-6">Follow the steps to complete registration</p>

        @if(session('status'))
            <div class="bg-green-800 text-white p-3 rounded mb-4 text-center">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" wire:submit.prevent="{{ $step === 1 ? 'nextStep' : 'register' }}" class="flex flex-col gap-5">

            {{-- Step 1 --}}
            @if($step === 1)
                <div class="space-y-4">

                    <div>
                        <label class="block mb-2 font-semibold">Full Name</label>
                        <input type="text" wire:model="name" required autofocus
                            placeholder="Juan Dela Cruz"
                            class="w-full rounded-xl p-3 bg-[#0E2F15] border border-[#1A5A20] text-white placeholder-green-300 focus:ring-2 focus:ring-[#31D67B]">
                    </div>

                    <div>
                        <label class="block mb-2 font-semibold">Email Address</label>
                        <input type="email" wire:model="email" required
                            placeholder="email@example.com"
                            class="w-full rounded-xl p-3 bg-[#0E2F15] border border-[#1A5A20] text-white placeholder-green-300 focus:ring-2 focus:ring-[#31D67B]">
                    </div>

                    <div class="flex justify-end">
                        <button type="submit"
                            class="bg-[#31D67B] hover:bg-[#2CB86D] text-[#0B260F] font-bold py-2 px-8 rounded-xl transition">
                            Next
                        </button>
                    </div>

                </div>
            @endif

            {{-- Step 2 --}}
            @if($step === 2)
                <div class="space-y-4">

                    <div>
                        <label class="block mb-2 font-semibold">Role</label>
                        <select wire:model="role" required
                            class="w-full rounded-xl p-3 bg-[#0E2F15] border border-[#1A5A20] text-white focus:ring-2 focus:ring-[#31D67B]">
                            <option value="">Select Role</option>
                            <option value="Manager">Manager</option>
                            <option value="Employee">Employee</option>
                        </select>
                    </div>

                    <div>
                        <label class="block mb-2 font-semibold">Password</label>
                        <input type="password" wire:model="password" required placeholder="Password"
                            class="w-full rounded-xl p-3 bg-[#0E2F15] border border-[#1A5A20] text-white placeholder-green-300 focus:ring-2 focus:ring-[#31D67B]">
                    </div>

                    <div>
                        <label class="block mb-2 font-semibold">Confirm Password</label>
                        <input type="password" wire:model="password_confirmation" required placeholder="Confirm password"
                            class="w-full rounded-xl p-3 bg-[#0E2F15] border border-[#1A5A20] text-white placeholder-green-300 focus:ring-2 focus:ring-[#31D67B]">
                    </div>

                    <div class="flex justify-between">
                        <button type="button" wire:click="prevStep"
                            class="bg-green-700 hover:bg-green-600 text-white font-semibold py-2 px-6 rounded-xl transition">
                            Back
                        </button>

                        <button type="submit"
                            class="bg-[#31D67B] hover:bg-[#2CB86D] text-[#0B260F] font-bold py-2 px-8 rounded-xl transition">
                            Register
                        </button>
                    </div>
                </div>
            @endif

        </form>

        <div class="text-center text-sm text-green-200 mt-6">
            Already have an account?
            <a href="{{ route('login') }}" class="font-semibold text-[#C8FFD4] hover:underline">
                Log in
            </a>
        </div>
    </div>
</div>
