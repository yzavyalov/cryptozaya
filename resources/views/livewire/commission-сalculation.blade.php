<div>
    <h3 class="m-3">Commission Calculation</h3>

    @if (session()->has('error'))
        <div class="alert alert-danger mx-3 mb-3">
            {{ session('error') }}
        </div>
    @endif

    <div class="card shadow-sm p-3 w-100">

        <form wire:submit.prevent="recalculate">

            {{-- ADDRESSES --}}
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">From Wallet</label>
                    <input
                        wire:model.defer="from"
                        type="text"
                        class="form-control"
                        placeholder="Sender address">
                </div>

                <div class="col-md-6">
                    <label class="form-label">To Wallet</label>
                    <input
                        wire:model.defer="to"
                        type="text"
                        class="form-control"
                        placeholder="Recipient address">
                </div>
            </div>

            {{-- AMOUNT --}}
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label">Amount</label>
                    <input
                        wire:model.defer="amount"
                        type="number"
                        step="any"
                        class="form-control"
                        placeholder="Amount">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Blockchain</label>
                    <select wire:model.defer="blockchain" class="form-select">
                        <option value="">Select blockchain</option>
                        @foreach(\App\Http\Enums\BlockChainEnum::cases() as $chain)
                            <option value="{{ $chain->label() }}">
                                {{ strtoupper($chain->label()) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Currency</label>
                    <select wire:model.defer="currency" class="form-select">
                        <option value="">Select currency</option>
                        @foreach($currencies as $currency)
                            <option value="{{ $currency->id }}">
                                {{ $currency->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- FEES --}}
            @if($totalAmount !== null)
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered mb-0">
                        <tbody>
                        <tr>
                            <th>Network Fee</th>
                            <td class="text-end">
                                {{ number_format($networkFee, 6) }} {{ $feeCurrency }}
                            </td>
                        </tr>
                        <tr>
                            <th>Service Fee</th>
                            <td class="text-end">
                                {{ number_format($serviceFee, 6) }} {{ $feeCurrency }}
                            </td>
                        </tr>
                        <tr class="table-success">
                            <th>Total Fee</th>
                            <td class="text-end fw-bold">
                                {{ number_format($totalAmount, 6) }} {{ $feeCurrency }}
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- ACTION --}}
            <div class="d-flex justify-content-end">
                <button
                    class="btn btn-primary"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove>Calculate</span>
                    <span wire:loading>Calculating…</span>
                </button>
            </div>

        </form>
    </div>
</div>
