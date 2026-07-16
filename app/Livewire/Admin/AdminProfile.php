<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Back-office : gestion du compte admin connecte. Permet de changer son adresse email et son mot de
 * passe (le changement de mot de passe exige le mot de passe actuel). Retours via toast ephemere.
 */
#[Layout('layouts.admin')]
class AdminProfile extends Component
{
    public string $email = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $this->email = (string) $this->user()->email;
    }

    public function updateEmail(): void
    {
        $user = $this->user();

        $this->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ], [
            'email.required' => __('L\'adresse email est obligatoire.'),
            'email.email' => __('Veuillez saisir une adresse email valide.'),
            'email.unique' => __('Cette adresse email est déjà utilisée.'),
        ]);

        $user->email = $this->email;
        $user->save();

        $this->dispatch('admin-toast', message: __('Adresse email mise à jour.'), type: 'success');
    }

    public function updatePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.required' => __('Le mot de passe actuel est obligatoire.'),
            'current_password.current_password' => __('Le mot de passe actuel est incorrect.'),
            'password.required' => __('Le nouveau mot de passe est obligatoire.'),
            'password.min' => __('Le nouveau mot de passe doit contenir au moins 8 caractères.'),
            'password.confirmed' => __('La confirmation ne correspond pas au nouveau mot de passe.'),
        ]);

        $user = $this->user();
        $user->password = $this->password;
        $user->save();

        $this->reset('current_password', 'password', 'password_confirmation');
        $this->dispatch('admin-toast', message: __('Mot de passe mis à jour.'), type: 'success');
    }

    public function render(): View
    {
        return view('livewire.admin.profile')->title(__('Mon compte').' · Back-office Festilaw');
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
