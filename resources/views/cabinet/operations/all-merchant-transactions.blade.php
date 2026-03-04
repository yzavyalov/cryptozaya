@extends('cabinet.layout.template')

@section('content')
    <div class="container mt-4">
        <h3 class="mb-3">Merchants' transactions</h3>

        {{-- ✅ Response block (once) --}}
        @if(session()->has('callback_response'))
            @php
                $cb = session('callback_response');
                $ok = (bool)($cb['success'] ?? false);
                $status = $cb['status'] ?? null;

                $alertClass = $ok ? 'alert-success' : 'alert-danger';
                $title = $ok ? 'Callback sent successfully' : 'Callback failed';

                $payload = $cb['data'] ?? ($cb['error'] ?? $cb);
                $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            @endphp

            <div id="callback-alert" class="alert {{ $alertClass }} mb-3">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div class="fw-semibold">
                        {{ $title }}
                        <span class="text-muted ms-2 small">
                            HTTP: <span class="fw-semibold">{{ $status ?? 'N/A' }}</span>
                        </span>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="button"
                                class="btn btn-sm btn-outline-secondary"
                                onclick="toggleCallback()"
                                id="toggle-btn">
                            Show more
                        </button>

                        <button type="button"
                                class="btn btn-sm btn-outline-danger"
                                onclick="closeCallback()">
                            Close
                        </button>
                    </div>
                </div>

                <hr class="my-2">

                <pre id="callback-content"
                     data-full="{{ e($json) }}"
                     class="mb-0 small"
                     style="white-space: pre-wrap;"></pre>
            </div>
        @endif

        <div class="card shadow-sm">
            <div class="card-body p-0">
                {{-- ✅ Scroll container so table stays normal + bottom scrollbar --}}
                <div class="table-responsive" style="overflow-x:auto;">
                    <table class="table table-striped table-hover mb-0 text-nowrap">
                        <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Merchant</th>
                            <th>type_transactions</th>
                            <th style="width: 120px;"></th>
                            <th>status</th>
                            <th>network</th>
                            <th>wallet_from</th>
                            <th>wallet_to</th>
                            <th>merchant_system_user_id</th>
                            <th>merchant_system_transaction_id</th>
                            <th>amount</th>
                            <th>currency</th>
                        </tr>
                        </thead>

                        <tbody>
                        @forelse ($transactions as $transaction)
                            <tr>
                                <td>{{ $transaction->id }}</td>
                                <td>{{ $transaction->merchant->name }}</td>

                                <td>
                                    {{ \App\Http\Enums\MerchantTypeTransactionEnum::from($transaction->type_transactions)->name }}
                                </td>

                                <td>
                                    @if($transaction->type_transactions === \App\Http\Enums\MerchantTypeTransactionEnum::deposit->value)
                                        <a href="{{ route('send-transaction-callback', $transaction->id) }}"
                                           class="btn btn-sm btn-primary">
                                            Send CB
                                        </a>
                                    @endif
                                </td>

                                <td>{{ \App\Http\Enums\MerchantTransactionStatusEnum::from($transaction->status)->name }}</td>
                                <td>{{ $transaction->network }}</td>
                                <td>{{ $transaction->wallet_from }}</td>
                                <td>{{ $transaction->wallet_to }}</td>
                                <td>{{ $transaction->merchant_system_user_id }}</td>
                                <td>{{ $transaction->merchant_system_transaction_id }}</td>
                                <td>{{ $transaction->sum }}</td>
                                <td>{{ $transaction->currency->name }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="text-center text-muted py-4">No transactions</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ✅ Pagination --}}
            <div class="card-footer bg-white">
                <div class="d-flex justify-content-center">
                    {{ $transactions->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>

    {{-- ✅ Response show more/less + close --}}
    <script>
        const limit = 300;

        function initCallback() {
            const el = document.getElementById('callback-content');
            const btn = document.getElementById('toggle-btn');
            if (!el) return;

            const full = el.dataset.full || '';

            if (full.length <= limit) {
                el.textContent = full;
                if (btn) btn.style.display = 'none';
                return;
            }

            el.dataset.short = full.substring(0, limit) + '...';
            el.dataset.expanded = 'false';
            el.textContent = el.dataset.short;
        }

        function toggleCallback() {
            const el = document.getElementById('callback-content');
            const btn = document.getElementById('toggle-btn');
            if (!el || !btn) return;

            if (el.dataset.expanded === 'true') {
                el.textContent = el.dataset.short;
                el.dataset.expanded = 'false';
                btn.innerText = 'Show more';
            } else {
                el.textContent = el.dataset.full;
                el.dataset.expanded = 'true';
                btn.innerText = 'Show less';
            }
        }

        function closeCallback() {
            const block = document.getElementById('callback-alert');
            if (block) block.remove();
        }

        document.addEventListener('DOMContentLoaded', initCallback);
    </script>
@endsection
