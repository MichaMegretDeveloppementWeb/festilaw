<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Connexion au back-office. Auth par session (garde web), sans inscription publique (comptes crees
 * par la commande festilaw:create-admin). Limite par IP contre le bruteforce.
 */
#[Layout('layouts.admin')]
#[Title('Connexion · Back-office Festilaw')]
class LoginForm extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    /** @return array<string, array<int, string>> */
    protected function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'email.required' => __('L\'adresse email est obligatoire.'),
            'email.email' => __('Veuillez saisir une adresse email valide.'),
            'password.required' => __('Le mot de passe est obligatoire.'),
        ];
    }

    public function login(): mixed
    {
        $this->validate();

        $key = 'admin-login:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('email', __('Trop de tentatives. Réessayez dans une minute.'));

            return null;
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($key, 60);
            $this->addError('email', __('Identifiants invalides.'));

            return null;
        }

        RateLimiter::clear($key);
        session()->regenerate();

        return $this->redirectRoute('admin.submissions.index');
    }

    public function render(): View
    {
        return view('livewire.admin.login-form');
    }
}
