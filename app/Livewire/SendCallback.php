<?php

namespace App\Livewire;

use App\Models\Merchant;
use App\Services\Operations\MerchantWallet\MerchantWebhookService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Throwable;

class SendCallback extends Component
{
    /** @var Collection<int, Merchant> */
    public Collection $merchants;

    public ?int $openMerchantId = null;

    /** deposit|withdraw */
    public string $eventType = 'deposit';

    /**
     * Результаты по каждому мерчанту:
     * [
     *   12 => ['success'=>true,'status'=>200,'data'=>[...] ],
     *   15 => ['success'=>false,'status'=>500,'error'=>'...' ],
     * ]
     */
    public array $callbackResults = [];

    /** ID мерчанта, по которому сейчас идет запрос */
    public ?int $sendingMerchantId = null;

    public function mount(): void
    {
        $this->merchants = Auth::user()?->merchants ?? collect();
    }

    public function toggleForm(int $merchantId): void
    {
        if ($this->openMerchantId === $merchantId) {
            $this->openMerchantId = null;
            $this->eventType = 'deposit';
            return;
        }

        $this->openMerchantId = $merchantId;
        $this->eventType = 'deposit';

        // Сбрасываем прошлый результат именно для этой строки (чтобы не путаться)
        unset($this->callbackResults[$merchantId]);
    }

    public function sendTestCallback(int $merchantId): void
    {
        // Закрытая форма -> не шлем
        if ($this->openMerchantId !== $merchantId) {
            $this->openMerchantId = $merchantId;
        }

        $merchant = $this->merchants->firstWhere('id', $merchantId);

        if (!$merchant) {
            $this->callbackResults[$merchantId] = $this->failResult('Merchant not found in current list.');
            return;
        }

        if (empty($merchant->cburl)) {
            $this->callbackResults[$merchantId] = $this->failResult('Merchant cburl is empty.');
            return;
        }

        $this->sendingMerchantId = $merchantId;

        try {
            /** @var MerchantWebhookService $service */
            $service = app(MerchantWebhookService::class);

            $raw = $service->sendExampleDepositCallback($merchant);

            $this->callbackResults[$merchantId] = $this->normalizeResult($raw);

        } catch (Throwable $e) {
            Log::error('Send test callback failed', [
                'merchant_id' => $merchantId,
                'eventType'   => $this->eventType,
                'error'       => $e->getMessage(),
            ]);

            $this->callbackResults[$merchantId] = $this->failResult($e->getMessage());
        } finally {
            $this->sendingMerchantId = null;
        }
    }

    public function clearResult(int $merchantId): void
    {
        unset($this->callbackResults[$merchantId]);
    }

    /**
     * Приводим любой ответ сервиса к единому виду:
     * - success (bool)
     * - status (int|null)
     * - data (mixed) или error (string|mixed)
     */
    private function normalizeResult(mixed $raw): array
    {
        // Если сервис вернул не массив — считаем успехом (но без статуса)
        if (!is_array($raw)) {
            return [
                'success' => true,
                'status'  => null,
                'data'    => $raw,
            ];
        }

        $status = $raw['status'] ?? null;
        $hasExplicitSuccess = array_key_exists('success', $raw);

        // Если success не передали — выводим из HTTP status (если он есть)
        if (!$hasExplicitSuccess) {
            if (is_int($status)) {
                $raw['success'] = $status >= 200 && $status < 300;
            } else {
                // нет статуса — по умолчанию успех
                $raw['success'] = true;
            }
        }

        // Если статус есть и он 4xx/5xx — success обязан быть false
        if (is_int($status) && $status >= 400) {
            $raw['success'] = false;
        }

        // Если это fail, но нет error — положим что-нибудь в error
        if (($raw['success'] ?? false) === false && !array_key_exists('error', $raw)) {
            $raw['error'] = $raw['data'] ?? 'Request failed';
        }

        return $raw;
    }

    private function failResult(string $message, ?int $status = null): array
    {
        return [
            'success' => false,
            'status'  => $status,
            'error'   => $message,
        ];
    }

    public function render()
    {
        return view('livewire.send-callback');
    }
}
