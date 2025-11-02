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
<style>
    .reg-wrapper {
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 24px;
    background: #f3f4f6;
}

/* Card styling */
.reg-card {
    width: 100%;
    max-width: 420px;
    background: rgba(18, 65, 22, 0.95);
    border: 1px solid #1A5A20;
    backdrop-filter: blur(12px);
    border-radius: 16px;
    padding: 32px;
    color: white;
    box-shadow: 0 15px 35px rgba(0,0,0,0.25);
}

/* Titles */
.reg-title {
    font-size: 24px;
    font-weight: 800;
    margin-bottom: 4px;
}

.reg-subtitle {
    color: #9CF3B3;
    margin-bottom: 20px;
}

/* Success alert */
.alert-success {
    background: #14532d;
    color: white;
    padding: 10px;
    border-radius: 8px;
    text-align: center;
    margin-bottom: 16px;
}

/* Form */
.reg-form {
    display: flex;
    flex-direction: column;
    gap: 18px;
}

/* Input group */
.input-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.input-group label {
    font-weight: 600;
    color: #C8FFD4;
}

.input-group input,
.input-group select {
    padding: 12px;
    border-radius: 10px;
    background: #0E2F15;
    border: 1px solid #1A5A20;
    color: white;
}

.input-group input:focus,
.input-group select:focus {
    border-color: #31D67B;
    box-shadow: 0 0 0 2px rgba(49,214,123,0.4);
    outline: none;
}

/* Buttons */
.btn-primary {
    background: #31D67B;
    color: #0B260F;
    font-weight: 700;
    border: none;
    padding: 10px 24px;
    border-radius: 12px;
    cursor: pointer;
    transition: .2s;
}

.btn-primary:hover {
    background: #2CB86D;
}

.btn-dark {
    background: #166534;
    color: white;
    font-weight: 600;
    border: none;
    padding: 10px 22px;
    border-radius: 12px;
    cursor: pointer;
    transition: .2s;
}

.btn-dark:hover {
    background: #15803d;
}

/* Button alignment */
.actions-right {
    text-align: right;
}

.actions-between {
    display: flex;
    justify-content: space-between;
}

/* Footer */
.reg-footer {
    text-align: center;
    color: #9CF3B3;
    font-size: 14px;
    margin-top: 20px;
}

.reg-footer a {
    color: #C8FFD4;
    font-weight: 700;
    text-decoration: none;
}

.reg-footer a:hover {
    text-decoration: underline;
}
</style>
<div class="reg-wrapper">
    <div class="reg-card">

        <h2 class="reg-title">Create an account</h2>
        <p class="reg-subtitle">Follow the steps to complete registration</p>

        @if(session('status'))
            <div class="alert-success">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" wire:submit.prevent="{{ $step === 1 ? 'nextStep' : 'register' }}" class="reg-form">

            @if($step === 1)
                <div>

                    <div class="input-group">
                        <label>Full Name</label>
                        <input type="text" wire:model="name" placeholder="Juan Dela Cruz" required>
                    </div>

                    <div class="input-group">
                        <label>Email Address</label>
                        <input type="email" wire:model="email" placeholder="email@example.com" required>
                    </div>

                    <div class="actions-right">
                        <button type="submit" class="btn-primary">Next</button>
                    </div>

                </div>
            @endif

            @if($step === 2)
                <div>

                    <div class="input-group">
                        <label>Role</label>
                        <select wire:model="role" required>
                            <option value="">Select Role</option>
                            <option value="Manager">Manager</option>
                            <option value="Employee">Employee</option>
                        </select>
                    </div>

                    <div class="input-group">
                        <label>Password</label>
                        <input type="password" wire:model="password" placeholder="Password" required>
                    </div>

                    <div class="input-group">
                        <label>Confirm Password</label>
                        <input type="password" wire:model="password_confirmation" placeholder="Confirm Password" required>
                    </div>

                    <div class="actions-between">
                        <button type="button" wire:click="prevStep" class="btn-dark">Back</button>
                        <button type="submit" class="btn-primary">Register</button>
                    </div>

                </div>
            @endif

        </form>

        <div class="reg-footer">
            Already have an account?
            <a href="{{ route('login') }}">Log in</a>
        </div>
    </div>
</div>

