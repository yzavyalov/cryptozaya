<?php

namespace App\Livewire;

use App\Services\TwoFactorService;
use Livewire\Component;

class Enterform extends Component
{
    public bool $codeSent = false;
    public string $email = '';
    public string $code = '';
    public int $codeTtl = 600; // 10 минут
    public ?int $expiresAt = null;

    protected ?TwoFactorService $twoFactorService = null;

    public function mount(TwoFactorService $twoFactorService)
    {
        $this->twoFactorService = $twoFactorService;
    }

    protected function rules(): array
    {
        // если код уже отправлен — валидируем оба поля
        if ($this->codeSent) {
            return [
                'email' => 'required|email',
                'code'  => 'required|string|max:6',
            ];
        }

        // если код ещё не отправлен — только email
        return [
            'email' => 'required|email',
        ];
    }

    public function sendCode()
    {
        $this->validate();

        $service = $this->twoFactorService ?? app(TwoFactorService::class);
        $result = $service->enter($this->email);

        if (!is_array($result) || empty($result['cachename'])) {
            $this->addError('email', $result['message'] ?? 'Ошибка при отправке кода');
            return;
        }

        $this->codeSent = true;
        $this->expiresAt = now()->addSeconds($this->codeTtl)->timestamp;
    }

    public function resendCode()
    {
        $service = $this->twoFactorService ?? app(TwoFactorService::class);
        $result = $service->enter($this->email);

        if (!is_array($result) || empty($result['cachename'])) {
            $this->addError('email', $result['message'] ?? 'Ошибка при повторной отправке');
            return;
        }

        $this->expiresAt = now()->addSeconds($this->codeTtl)->timestamp;
    }

    public function verifyCode()
    {
        $this->validate();

        $service = $this->twoFactorService ?? app(TwoFactorService::class);
        $result = $service->verify($this->code, $this->email);

        if (!$result) {
            $this->addError('code', 'Неверный код!');
            return;
        }

        return redirect()->route('cabinet');
    }

    public function getRemainingTimeProperty(): int
    {
        if (!$this->expiresAt) return 0;
        return max(0, $this->expiresAt - now()->timestamp);
    }

    public function getFormattedTimeProperty(): string
    {
        $seconds = $this->remainingTime;
        return sprintf('%02d:%02d', floor($seconds / 60), $seconds % 60);
    }

    public function tick() {}

    public function render()
    {
        return view('livewire.enterform');
    }
}
