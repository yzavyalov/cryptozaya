<div>
    <h3 class="mb-3">My Balances</h3>
    <div>
        <button type="submit" onclick="window.location='{{ route('topup-form') }}'">Top up</button>
    </div>
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-striped table-hover mb-0">
                <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>User ID</th>
                    <th>Email</th>
                    <th>Currency</th>
                    <th>Balance</th>
                    <th></th>
                </tr>
                </thead>

                <tbody wire:poll.10s="loadBalance">
                @foreach ($balances as $balance)
                    <tr>
                        <td>{{ $balance->id }}</td>
                        <td>{{ $balance->user_id }}</td>
                        <td>{{ $balance->user->email }}</td>
                        <td>{{ $balance->currency }}</td>
                        <td>{{ number_format($balance->balance, 8, '.', ',') }}</td>
                        <td><button class="btn btn-info" onclick="window.location='{{ route('my-wallets') }}'">SEND</button></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <h3 class="mb-3">My merchants' balance</h3>

        <div class="card-body p-0">
            <table class="table table-striped table-hover mb-0">
                <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Merchant ID</th>
                    <th>Merchant's name</th>
                    <th>Currency</th>
                    <th>Balance</th>
                </tr>
                </thead>

                <tbody wire:poll.10s>
                @foreach ($merchantBalances as $merchantBalance)
                    <tr>
                        <td>{{ $merchantBalance->id }}</td>
                        <td>{{ $merchantBalance->merchant_id }}</td>
                        <td>{{ $merchantBalance->merchant->name }}</td>
                        <td>{{ $merchantBalance->currency }}</td>
                        <td>{{ number_format($merchantBalance->balance, 8, '.', ',') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>


    </div>
</div>
