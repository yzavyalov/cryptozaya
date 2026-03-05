<?php

namespace App\Livewire;

use App\Services\TwoFactorService;
use Livewire\Component;

class Enterform extends Component
{
    public bool $codeSent = false;

    public string $email = '';
    public string $code  = '';

    public int $codeTtl = 600; // seconds
    public ?int $expiresAt = null; // unix timestamp

    protected ?TwoFactorService $twoFactorService = null;

    public function mount(TwoFactorService $twoFactorService): void
    {
        $this->twoFactorService = $twoFactorService;
    }

    protected function rules(): array
    {
        if ($this->codeSent) {
            return [
                'email' => ['required', 'email'],
                'code'  => ['required', 'string', 'max:6'],
            ];
        }

        return [
            'email' => ['required', 'email'],
        ];
    }

    public function sendCode(): void
    {
        $this->resetErrorBag();
        $this->validate();

        $service = $this->twoFactorService ?? app(TwoFactorService::class);
        $result  = $service->enter($this->email);

        if (!is_array($result) || empty($result['cachename'])) {
            $this->addError('email', $result['message'] ?? 'Ошибка при отправке кода');
            return;
        }

        $this->codeSent  = true;
        $this->code      = '';
        $this->expiresAt = now()->addSeconds($this->codeTtl)->timestamp;
    }

    public function resendCode(): void
    {
        $this->resetErrorBag();

        // если email пустой — не шлём
        if (blank($this->email)) {
            $this->addError('email', 'Email is required');
            return;
        }

        $service = $this->twoFactorService ?? app(TwoFactorService::class);
        $result  = $service->enter($this->email);

        if (!is_array($result) || empty($result['cachename'])) {
            $this->addError('email', $result['message'] ?? 'Ошибка при повторной отправке');
            return;
        }

        $this->code      = '';
        $this->expiresAt = now()->addSeconds($this->codeTtl)->timestamp;

        // Скажем Alpine “таймер перезапустить”
        $this->dispatch('timer-reset', expiresAt: $this->expiresAt);
    }

    public function verifyCode()
    {
        $this->resetErrorBag();
        $this->validate();

        $service = $this->twoFactorService ?? app(TwoFactorService::class);

        $ok = $service->verify($this->code, $this->email);

        if (!$ok) {
            $this->addError('code', 'The code is wrong!');
            return;
        }

        // В Livewire 3 — только так
        return $this->redirectRoute('cabinet');
    }

    public function render()
    {
        return view('livewire.enterform');
    }
}
