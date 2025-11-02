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
            $this->addError('email', __('auth.failed')); // ✅ Show Wrong email/password error

            return;
        }

        // Role
        $role = $user->role ?? DB::table('hr_employees')->where('email', $user->email)->value('role');

        if (!$role) {
            $this->addError('email', 'Your account does not have a valid role assigned.');
            return;
        }

        Auth::login($user, $this->remember);
        RateLimiter::clear($this->throttleKey());
        Session::regenerate();

        if ($role === 'Admin') {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
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
<style>
    .login-wrapper {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    background: #f3f4f6;
}

/* Card */
.login-card {
    width: 100%;
    max-width: 420px;
    background: rgba(18, 65, 22, 0.95);
    backdrop-filter: blur(12px);
    border-radius: 16px;
    padding: 32px;
    border: 1px solid #1A5A20;
    box-shadow: 0 15px 35px rgba(0,0,0,0.2);
}

/* Title */
.login-title h2 {
    text-align: center;
    font-size: 28px;
    font-weight: 800;
    color: #C8FFD4;
    margin: 0;
}

.login-title p {
    text-align: center;
    font-size: 14px;
    margin-top: 4px;
    font-weight: 600;
    color: #9CF3B3;
}

/* Form */
.login-form {
    margin-top: 24px;
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.input-group label {
    color: #C8FFD4;
    font-weight: 600;
    margin-bottom: 4px;
    display: block;
}

.input-group input {
    width: 100%;
    padding: 10px 14px;
    border-radius: 8px;
    background: #0E2F15;
    border: 1px solid #1A5A20;
    color: white;
    font-size: 15px;
    outline: none;
    box-shadow: 0 2px 5px rgba(0,0,0,0.3);
}

.input-group input:focus {
    border-color: #31D67B;
    box-shadow: 0 0 0 2px rgba(49,214,123,0.5);
}

/* Forgot password */
.forgot-link {
    font-size: 13px;
    color: #9CF3B3;
    display: inline-block;
    margin-top: 6px;
    text-decoration: none;
}

.forgot-link:hover {
    text-decoration: underline;
}

/* Remember */
.remember {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #C8FFD4;
    font-size: 14px;
    font-weight: 500;
}

/* Button */
.login-btn {
    width: 100%;
    padding: 12px;
    border-radius: 12px;
    font-weight: 700;
    background: #31D67B;
    color: #0B260F;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 10px rgba(0,0,0,0.25);
    transition: 0.2s;
}

.login-btn:hover {
    background: #2CB86D;
}

.login-btn:active {
    transform: scale(0.98);
}

/* Register link */
.register-text {
    text-align: center;
    font-size: 14px;
    margin-top: 20px;
    color: #9CF3B3;
}

.register-text a {
    font-weight: 700;
    color: #C8FFD4;
    text-decoration: none;
}

.register-text a:hover {
    text-decoration: underline;
}

</style>
<div class="login-wrapper">
    <div class="login-card">

        <div class="login-title">
            <h2>Welcome Back</h2>
            <p>Sign in to your account</p>
        </div>

        <form wire:submit="login" method="POST" class="login-form">

            <div class="input-group">
                <label>Email</label>
                <input wire:model="email" type="email" placeholder="you@example.com">
            </div>

            <div class="input-group">
                <label>Password</label>
                <input wire:model="password" type="password" placeholder="••••••••">

                @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" wire:navigate class="forgot-link">Forgot Password?</a>
                @endif
            </div>

            <label class="remember">
                <input type="checkbox" wire:model="remember">
                Remember me
            </label>

            <button type="submit" class="login-btn">Log In</button>
        </form>

        <div class="register-text">
            Don't have an account?
            <a href="{{ route('register') }}" wire:navigate>Create one</a>
        </div>

    </div>
</div>