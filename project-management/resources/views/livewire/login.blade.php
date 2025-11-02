<?php

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Features;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.landingapp')] class extends Component {
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->validate();
        $this->ensureIsNotRateLimited();
        $user = $this->validateCredentials();

        if (Features::canManageTwoFactorAuthentication() && $user->hasEnabledTwoFactorAuthentication()) {
            Session::put([
                'login.id' => $user->getKey(),
                'login.remember' => $this->remember,
            ]);

            $this->redirect(route('two-factor.login'), navigate: true);
            return;
        }

        Auth::login($user, $this->remember);
        RateLimiter::clear($this->throttleKey());
        Session::regenerate();
        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    protected function validateCredentials(): User
    {
        $user = Auth::getProvider()->retrieveByCredentials(['email' => $this->email, 'password' => $this->password]);

        if (! $user || ! Auth::getProvider()->validateCredentials($user, ['password' => $this->password])) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }
        return $user;
    }

    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) return;

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

    <div class="w-full max-w-md bg-white shadow-xl rounded-2xl p-8 border border-gray-200">

        <!-- Title -->
        <div class="text-center mb-8">
            <h2 class="text-3xl font-extrabold tracking-tight text-gray-800">
                Welcome Back
            </h2>
            <p class="text-sm mt-1 font-medium text-gray-500">
                Sign in to your account
            </p>
        </div>

        <!-- Login Form -->
        <form wire:submit="login" method="POST" class="space-y-5">

            <!-- Email -->
            <div>
                <label class="block font-semibold mb-1 text-gray-700">Email</label>
                <input
                    wire:model="email"
                    type="email"
                    placeholder="you@example.com"
                    class="w-full rounded-lg px-4 py-2 bg-gray-50 border border-gray-300
                           text-gray-900 shadow-sm focus:ring-2 focus:ring-blue-500 
                           outline-none transition-all"
                >
            </div>

            <!-- Password -->
            <div>
                <label class="block font-semibold mb-1 text-gray-700">Password</label>
                <input
                    wire:model="password"
                    type="password"
                    placeholder="••••••••"
                    class="w-full rounded-lg px-4 py-2 bg-gray-50 border border-gray-300
                           text-gray-900 shadow-sm focus:ring-2 focus:ring-blue-500 
                           outline-none transition-all"
                >

                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" wire:navigate
                       class="text-sm font-medium mt-1 inline-block text-blue-600 hover:underline">
                        Forgot Password?
                    </a>
                @endif
            </div>

            <!-- Remember -->
            <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                <input type="checkbox" wire:model="remember" class="accent-blue-600">
                Remember me
            </label>

            <!-- Login Button -->
            <button
                type="submit"
                class="w-full py-3 rounded-xl font-bold text-white shadow-lg transition-all
                       bg-blue-600 hover:bg-blue-700 active:scale-[0.98]"
            >
                Log In
            </button>
        </form>

        <!-- Register -->
        <div class="text-center text-sm mt-6 text-gray-600">
            Don't have an account?
            <a href="{{ route('register') }}" wire:navigate
               class="font-semibold text-blue-600 hover:underline">
                Create one
            </a>
        </div>
    </div>
</div>
