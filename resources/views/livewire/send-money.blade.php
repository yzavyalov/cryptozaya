<div class="container py-3">

    <h3 class="mb-4 fw-bold">Send money</h3>

    {{-- GLOBAL ERROR DISPLAY --}}
    @if (session()->has('error'))
        <div class="alert alert-danger mb-3">
            <strong>Error:</strong> {{ session('error') }}
        </div>
    @endif

    <div class="card shadow-sm p-4">

        {{-- WALLET INFO --}}
        <div class="mb-4">
            <h5 class="fw-semibold">Your wallet:</h5>
            <div class="ps-2">
                <div><strong>Address:</strong> {{ $wallet->number }}</div>
            </div>

            <h6 class="fw-semibold mt-3">Blockchain balances:</h6>
            <div class="ps-2">
                <div>TRX — <strong>{{ $walletBalances['TRX'] ?? '—' }}</strong></div>
                <div>USDT (TRC20) — <strong>{{ $walletBalances['USDT'] ?? '—' }}</strong></div>
                <div>USDC (TRC20) — <strong>{{ $walletBalances['USDC'] ?? '—' }}</strong></div>
            </div>
        </div>

        {{-- SEND FORM --}}
        <form wire:submit.prevent="sendMoney">
            @csrf

            <!-- ROW 1 -->
            <div class="row g-3 mb-2">
                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold">Blockchain</label>
                    <select wire:model="blockchain" class="form-select">
                        <option value="">Select blockchain</option>
                        @foreach(\App\Http\Enums\BlockChainEnum::cases() as $chain)
                            <option value="{{ $chain->label() }}">{{ $chain->label() }}</option>
                        @endforeach
                    </select>
                    @error('blockchain') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold">Currency</label>
                    <select wire:model="currency" class="form-select">
                        <option value="">Select currency</option>
                        @foreach($currencies as $currency)
                            <option value="{{ $currency->id }}">{{ $currency->name }}</option>
                        @endforeach
                    </select>
                    @error('currency') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold">Amount</label>
                    <input wire:model="amount" type="text" class="form-control">
                    @error('amount') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
            </div>

            <!-- ROW 2 -->
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-8">
                    <label class="form-label fw-semibold">Recipient address</label>
                    <input wire:model="to" type="text" class="form-control">
                    @error('to') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>

                <div class="col-12 col-md-4 d-grid">
                    <button type="submit" class="btn btn-primary py-2">
                        SEND
                    </button>
                </div>
            </div>
        </form>


    </div>
</div>
