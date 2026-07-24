<?php

namespace App\Livewire\MessengerVolt;

use App\Services\UserService;

trait SettingsActions
{
    public string $profileName = '';
    public $profileAvatar = null;

    public function updateProfile()
    {
        $this->validate([
            'profileName' => 'required|string|max:255',
        ]);

        $userService = app(UserService::class);
        $userService->updateProfile(auth()->id(), $this->profileName);

        if ($this->profileAvatar) {
            $userService->updateAvatar(auth()->id(), $this->profileAvatar);
            $this->profileAvatar = null;
        }

        $this->dispatch('profile-updated');
    }

    public function generateNewKey()
    {
        return app(UserService::class)->rotateKey(auth()->id());
    }

    public function saveEncryptedMasterKey(string $encryptedString)
    {
        auth()->user()->update([
            'master_key' => $encryptedString,
        ]);
    }

    public function getEncryptedMasterKey()
    {
        return (string) auth()->user()->master_key;
    }
}
