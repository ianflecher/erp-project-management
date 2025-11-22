<?php

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.landingapp')] class extends Component
{
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public function login(): void
{
    $this->validate();
    $this->ensureIsNotRateLimited();

    $user = Auth::getProvider()->retrieveByCredentials([
        'email' => $this->email,
        'password' => $this->password
    ]);

    if (!$user || !Auth::getProvider()->validateCredentials($user, ['password' => $this->password])) {
        RateLimiter::hit($this->throttleKey());
        $this->addError('email', __('auth.failed'));
        return;
    }

    // Get role from hr_employees if not directly on user model
    $role = $user->role ?? DB::table('hr_employees')->where('email', $user->email)->value('role');

    if (!$role) {
        $this->addError('email', 'Your account does not have a valid role assigned.');
        return;
    }

    Auth::login($user, $this->remember);
    RateLimiter::clear($this->throttleKey());
    Session::regenerate();

    // REDIRECT BASED ON ROLE
    if ($role === 'Admin') {
        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

    } elseif ($role === 'Finance') {
        // Finance redirect
        $this->redirectIntended(default: route('finance', absolute: false), navigate: true);

    } elseif ($role === 'Manager' || $role === 'Employee') {
        $this->redirectIntended(default: route('employee', absolute: false), navigate: true);

    } else {
        $this->addError('email', 'Your account role is not recognized.');
    }
}


    protected function ensureIsNotRateLimited(): void
    {
        if (!RateLimiter::tooManyAttempts($this->throttleKey(), 5)) return;

        event(new Lockout(request()));
        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }
};
?>
<div class="min-h-screen flex items-center justify-center p-6 bg-gray-100">

    <div class="w-full max-w-md bg-[#124116]/95 backdrop-blur-xl shadow-2xl rounded-2xl p-8 border border-[#1A5A20]">

        <!-- Title -->
        <div class="text-center mb-8">
            <h2 class="text-3xl font-extrabold tracking-tight text-[#C8FFD4]">
                Welcome Back
            </h2>
            <p class="text-sm mt-1 font-medium text-[#9CF3B3]">
                Sign in to your account
            </p>
        </div>

        <!-- Login Form -->
        <form wire:submit="login" method="POST" class="space-y-5">

            <!-- Email -->
            <div>
                <label class="block font-semibold mb-1 text-[#C8FFD4]">Email</label>
                <input
                    wire:model="email"
                    type="email"
                    placeholder="you@example.com"
                    class="w-full rounded-lg px-4 py-2 bg-[#0E2F15] border border-[#1A5A20]
                           text-white shadow-md focus:ring-2 focus:ring-[#31D67B] 
                           outline-none transition-all"
                >
            </div>

            <!-- Password -->
            <div>
                <label class="block font-semibold mb-1 text-[#C8FFD4]">Password</label>
                <input
                    wire:model="password"
                    type="password"
                    placeholder="••••••••"
                    class="w-full rounded-lg px-4 py-2 bg-[#0E2F15] border border-[#1A5A20]
                           text-white shadow-md focus:ring-2 focus:ring-[#31D67B] 
                           outline-none transition-all"
                >

                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" wire:navigate
                       class="text-sm font-medium mt-1 inline-block text-[#9CF3B3] hover:underline">
                        Forgot Password?
                    </a>
                @endif
            </div>

            <!-- Remember -->
            <label class="flex items-center gap-2 text-sm font-medium text-[#C8FFD4]">
                <input type="checkbox" wire:model="remember" class="accent-[#31D67B]">
                Remember me
            </label>

            <!-- Login Button -->
            <button
                type="submit"
                class="w-full py-3 rounded-xl font-bold text-[#0B260F] shadow-lg transition-all
                       bg-[#31D67B] hover:bg-[#2CB86D] active:scale-[0.98]"
            >
                Log In
            </button>
        </form>

        <!-- Register -->
        <div class="text-center text-sm mt-6 text-[#9CF3B3]">
            Don't have an account?
            <a href="{{ route('register') }}" wire:navigate
               class="font-semibold text-[#C8FFD4] hover:underline">
                Create one
            </a>
        </div>
    </div>
</div> 