@extends('cabinet.layout.template')

@section('content')
    <div class="container mt-4">
        <h3 class="mb-3">Merchants' Balances</h3>

        <div class="card shadow-sm">
            <div class="card-body p-0">
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Merchant's ID</th>
                        <th>Merchant's name</th>
                        <th>Currency</th>
                        <th>Balance</th>
                    </tr>
                    </thead>

                    <tbody>
                    @foreach ($balances as $balance)
                        <tr>
                            <td>{{ $balance->id }}</td>
                            <td>{{ $balance->merchant_id }}</td>
                            <td>{{ $balance->merchant->name }}</td>
                            <td>{{ $balance->currency }}</td>
                            <td>{{ number_format($balance->balance, 8, '.', ',') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

@endsection

