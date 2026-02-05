@extends('cabinet.layout.template')

@section('content')
    <div class="container mt-4">

        {{-- Заголовок --}}
        <h3 class="mb-4">Merchants' deposits</h3>

        {{-- Ошибки --}}
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Withdraw form --}}
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h4 class="mb-3">Withdraw deposits</h4>

                <form action="{{ route('withdraw.deposits') }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Select main wallet:
                        </label>

                        @forelse(Auth::user()->merchants as $merchant)
                            @if($merchant->mainWallet())
                                <div class="form-check">
                                    <input
                                        class="form-check-input"
                                        type="radio"
                                        name="wallet_id"
                                        id="wallet{{ $merchant->mainWallet()->id }}"
                                        value="{{ $merchant->mainWallet()->id }}"
                                        {{ old('wallet_id', $loop->first ? $merchant->mainWallet()->id : null) == $merchant->mainWallet()->id ? 'checked' : '' }}
                                    >

                                    <label class="form-check-label" for="wallet{{ $merchant->mainWallet()->id }}">
                                        <strong>{{ $merchant->mainWallet()->number }}</strong>
                                        <span class="text-muted">({{ $merchant->name }})</span>
                                    </label>
                                </div>
                            @endif
                        @empty
                            <div class="text-muted">
                                No merchants with main wallets available.
                            </div>
                        @endforelse
                    </div>

                    <button type="submit" class="btn btn-primary">
                        Withdraw Selected Wallet
                    </button>
                </form>
            </div>
        </div>

        {{-- Transactions table --}}
        <div class="card shadow-sm">
            <div class="card-body p-0">

                <table class="table table-striped table-hover mb-0">
                    <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Merchant</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Network</th>
                        <th>Wallet from</th>
                        <th>Wallet to</th>
                        <th>User ID</th>
                        <th>Tx ID</th>
                        <th>Amount</th>
                        <th>Currency</th>
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
                                {{ \App\Http\Enums\MerchantTransactionStatusEnum::from($transaction->status)->name }}
                            </td>
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
                            <td colspan="11" class="text-center text-muted py-4">
                                No transactions found
                            </td>
                        </tr>
                    @endforelse
                    </tbody>

                </table>

            </div>
        </div>

    </div>
@endsection

