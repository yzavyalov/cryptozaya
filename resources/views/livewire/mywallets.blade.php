<div>
    <h3 class="m-3">My Wallets</h3>
    <div>

        <div class="card shadow-sm p-3 w-100">

            <div class="d-flex justify-content-between align-items-center mb-3">
                <button wire:click="createWallet('tron')" class="btn btn-primary">
                    + Create new wallet
                </button>

                <button wire:click="loadWalletsBalancies" class="btn btn-outline-secondary">
                    🔄 Refresh balances
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0 w-100">
                    <thead class="table-dark">
                    <tr>
                        <th></th>
                        <th>Address</th>
                        <th>Network</th>
                        <th>TRX</th>
                        <th>USDT</th>
                        <th>USDC</th>
                        <th>Created</th>
                        <th style="width:100px;"></th>
                    </tr>
                    </thead>

                    <tbody>
                    @forelse($wallets as $wallet)
                        <tr>
                            <td>{{ \SimpleSoftwareIO\QrCode\Facades\QrCode::size(100)->generate($wallet['number']) }}</td>
                            <td class="text-break" style="max-width: 240px;">
                                {{ $wallet['number'] }}
                            </td>

                            <td>
                                <span class="badge bg-info text-dark">
                                    {{ strtoupper($wallet['network']) }}
                                </span>
                            </td>

                            <td>{{ $wallet['balances']['TRX'] ?? '—' }}</td>
                            <td>{{ $wallet['balances']['USDT'] ?? '—' }}</td>
                            <td>{{ $wallet['balances']['USDC'] ?? '—' }}</td>

                            <td>
                                {{ \Carbon\Carbon::parse($wallet['created_at'])->format('d.m.Y H:i') }}
                            </td>

                            <td class="text-end" style="width:100px;">
                                <button class="btn btn-sm btn-danger" onclick="window.location='{{ route('send-form',$wallet['id']) }}'">
                                    SEND
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                No wallets found
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

        </div>
</div>
</div>
