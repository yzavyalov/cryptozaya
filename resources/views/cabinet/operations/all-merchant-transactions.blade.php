@extends('cabinet.layout.template')

@section('content')
    <div class="container mt-4">
        <h3 class="mb-3">Merchants' transactions</h3>

        <div class="card shadow-sm">
            <div class="card-body p-0">
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Merchant</th>
                        <th>type_transactions</th>
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
                    @foreach ($transactions as $transaction)
                        <tr>
                            <td>{{ $transaction->id }}</td>
                            <td>{{ $transaction->merchant->name }}</td>
                            <td>{{ \App\Http\Enums\MerchantTypeTransactionEnum::from($transaction->type_transactions)->name }}</td>
                            <td>{{ \App\Http\Enums\MerchantTransactionStatusEnum::from($transaction->status)->name }}</td>
                            <td>{{ $transaction->network }}</td>
                            <td>{{ $transaction->wallet_from }}</td>
                            <td>{{ $transaction->wallet_to }}</td>
                            <td>{{ $transaction->merchant_system_user_id }}</td>
                            <td>{{ $transaction->merchant_system_transaction_id }}</td>
                            <td>{{ $transaction->sum }}</td>
                            <td>{{ $transaction->currency->name }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

@endsection

