<?php

namespace App\Livewire\Pages\Auth;

use Livewire\Component;
use App\Livewire\Actions\Logout;

class LogoutButton extends Component
{
    public function logout(Logout $logout)
    {
        $logout();
        return redirect('/');
    }

    public function render()
    {
        return view('livewire.pages.auth.logout-button');
    }
}