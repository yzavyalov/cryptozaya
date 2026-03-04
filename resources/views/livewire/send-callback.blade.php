<div class="p-3">
    <div class="card shadow-sm">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0">Merchants</h5>
            <span class="text-muted small">Total: {{ $merchants->count() }}</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th style="width: 140px;">Status</th>
                    <th>CB URL</th>
                    <th class="text-end" style="width: 220px;">Actions</th>
                </tr>
                </thead>

                <tbody>
                @forelse($merchants as $merchant)
                    @php
                        // Статус
                        $enum = \App\Http\Enums\MerchantStatusEnum::tryFrom($merchant->status);
                        $statusName = $enum?->name ?? 'Unknown';

                        $badgeClass = match ($statusName) {
                            'Active' => 'bg-success',
                            'Pending' => 'bg-warning text-dark',
                            'Disabled', 'Inactive' => 'bg-secondary',
                            default => 'bg-light text-dark border',
                        };

                        // Результат по текущему мерчанту (из компонента: callbackResults[merchantId])
                        $callbackResult = $callbackResults[$merchant->id] ?? null;
                    @endphp

                    <tr>
                        <td class="fw-semibold">
                            {{ $merchant->name }}
                        </td>

                        <td>
                            <span class="badge {{ $badgeClass }}">
                                {{ $statusName }}
                            </span>
                        </td>

                        <td class="text-muted">
                            <span class="d-inline-block text-truncate" style="max-width: 560px;" title="{{ $merchant->cburl }}">
                                {{ $merchant->cburl }}
                            </span>
                        </td>

                        <td class="text-end">
                            <button
                                type="button"
                                wire:click="toggleForm({{ $merchant->id }})"
                                class="btn btn-dark btn-sm"
                            >
                                Send test callback
                            </button>
                        </td>
                    </tr>

                    {{-- Раскрывающаяся строка --}}
                    @if($openMerchantId === $merchant->id)
                        <tr class="table-light">
                            <td colspan="4">
                                <div class="p-3">
                                    <div class="card border-0 shadow-sm">
                                        <div class="card-body">

                                            <div class="row g-3 align-items-end">
                                                <div class="col-12 col-md-6">
                                                    <label class="form-label fw-semibold mb-1">Event type</label>
                                                    <select wire:model="eventType" class="form-select">
                                                        <option value="deposit">Deposit</option>
                                                    </select>

                                                    @error('eventType')
                                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                                    @enderror
                                                </div>

                                                <div class="col-12 col-md-6 d-flex gap-2 justify-content-md-end">
                                                    <button
                                                        type="button"
                                                        wire:click="sendTestCallback({{ $merchant->id }})"
                                                        wire:loading.attr="disabled"
                                                        wire:target="sendTestCallback({{ $merchant->id }})"
                                                        class="btn btn-success"
                                                    >
                                                        <span wire:loading.remove wire:target="sendTestCallback({{ $merchant->id }})">Send</span>
                                                        <span wire:loading wire:target="sendTestCallback({{ $merchant->id }})">Sending...</span>
                                                    </button>

                                                    <button
                                                        type="button"
                                                        wire:click="toggleForm({{ $merchant->id }})"
                                                        class="btn btn-outline-secondary"
                                                    >
                                                        Cancel
                                                    </button>
                                                </div>
                                            </div>

                                            {{-- ✅ Вывод результата (успех/ошибка) --}}
                                            @if(!empty($callbackResult))
                                                @php
                                                    $ok = (bool)($callbackResult['success'] ?? false);
                                                    $status = $callbackResult['status'] ?? null;

                                                    $alertClass = $ok ? 'alert-success' : 'alert-danger';
                                                    $title = $ok ? 'Callback sent successfully' : 'Callback failed';

                                                    $jsonPayload = $callbackResult['data']
                                                        ?? ($callbackResult['error'] ?? $callbackResult);
                                                @endphp

                                                <div class="alert {{ $alertClass }} mt-3 mb-0">
                                                    <div class="d-flex align-items-center justify-content-between gap-2">
                                                        <div class="fw-semibold">{{ $title }}</div>
                                                        <div class="small">
                                                            <span class="text-muted">HTTP:</span>
                                                            <span class="fw-semibold">{{ $status ?? 'N/A' }}</span>
                                                        </div>
                                                    </div>

                                                    <hr class="my-2">

                                                    <pre class="mb-0 small" style="white-space: pre-wrap;">{{ json_encode($jsonPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>

                                                    <div class="mt-2 d-flex justify-content-end">
                                                        <button
                                                            type="button"
                                                            class="btn btn-outline-secondary btn-sm"
                                                            wire:click="clearResult({{ $merchant->id }})"
                                                        >
                                                            Clear
                                                        </button>
                                                    </div>
                                                </div>
                                            @endif

                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">
                            No merchants found
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script>
        window.addEventListener('notify', (e) => {
            alert(e.detail.message);
        });
    </script>
</div>
