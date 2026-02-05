<div>
    <h3 class="m-3 fw-bold">Wallet Balance</h3>

    {{-- GLOBAL ERROR --}}
    @if (session()->has('error'))
        <div class="alert alert-danger mx-3 mb-3">
            {{ session('error') }}
        </div>
    @endif

    <div class="card shadow-sm p-4">

        <form wire:submit.prevent="checkBalance" class="mb-4">

            <div class="mb-3">
                <label class="form-label fw-semibold">Wallet Address</label>
                <input
                    wire:model.defer="wallet"
                    type="text"
                    class="form-control"
                    placeholder="Enter TRON wallet address"
                >
                @error('wallet')
                <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="d-flex justify-content-end">
                <button class="btn btn-primary"
                        wire:loading.attr="disabled">
                    <span wire:loading.remove>Check Balance</span>
                    <span wire:loading>Checking...</span>
                </button>
            </div>
        </form>

        {{-- BALANCE RESULT --}}
        @if($balance)
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>Currency</th>
                        <th class="text-end">Balance</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($balance as $currency => $amount)
                        <tr>
                            <td class="fw-semibold">{{ $currency }}</td>
                            <td class="text-end">
                                {{ number_format((float)$amount, 6, '.', ' ') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="text-center text-muted">
                                No balances found
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        @endif

    </div>
</div>
