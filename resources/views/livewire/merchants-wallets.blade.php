<div>
    <div wire:poll.10s="refreshWallets">
        <h3 class="m-3">Mу merchants' main and withdraw wallets</h3>

        <div>
            <div class="card shadow-sm p-3 w-100">
                <div class="d-flex justify-content-end align-items-center mb-3">
                    <button wire:click="refreshWallets" class="btn btn-outline-secondary">
                        🔄 Refresh balances
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0 w-100">
                        <thead class="table-dark">
                        <tr>
                            <th></th>
                            <th>Address</th>
                            <th>Type</th>
                            <th>Network</th>
                            <th>TRX</th>
                            <th>USDT</th>
                            <th>USDC</th>
                            <th>Created</th>
                            <th style="width: 100px;"></th>
                        </tr>
                        </thead>

                        <tbody>

                        @forelse($wallets as $wallet)

                            <tr wire:key="wallet-{{ $wallet['id'] ?? md5(($wallet['number'] ?? '') . '-' . ($wallet['network'] ?? '')) }}">
                                <td>
                                    @if(!empty($wallet['number']))
                                        {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size(100)->generate($wallet['number']) !!}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>

                                <td class="text-break" style="max-width: 240px;">
                                    {{ $wallet['number'] ?? '—' }}
                                </td>

                                <td>
                                    @if($wallet['status'] === \App\Http\Enums\MerchantWalletStatusEnum::WITHDRAW->value)
                                        <span class="badge bg-warning text-dark px-3 py-2">
                                            💸 Withdraw
                                        </span>
                                    @elseif($wallet['status'] === \App\Http\Enums\MerchantWalletStatusEnum::MAIN->value)
                                        <span class="badge bg-success px-3 py-2">
                                            🏦 Main
                                        </span>
                                    @endif
                                </td>

                                <td>
                                    <span class="badge bg-info text-dark">
                                        {{ strtoupper($wallet['network'] ?? '—') }}
                                    </span>
                                </td>

                                <td>
                                    @if(!empty($wallet['balances']['error']))
                                        <span class="text-danger">{{ $wallet['balances']['error'] }}</span>
                                    @else
                                        {{ $wallet['balances']['TRX'] ?? '—' }}
                                    @endif
                                </td>

                                <td>
                                    @if(!empty($wallet['balances']['error']))
                                        <span class="text-muted">—</span>
                                    @else
                                        {{ $wallet['balances']['USDT'] ?? '—' }}
                                    @endif
                                </td>

                                <td>
                                    @if(!empty($wallet['balances']['error']))
                                        <span class="text-muted">—</span>
                                    @else
                                        {{ $wallet['balances']['USDC'] ?? '—' }}
                                    @endif
                                </td>

                                <td>
                                    @if(!empty($wallet['created_at']))
                                        {{ \Carbon\Carbon::parse($wallet['created_at'])->format('d.m.Y H:i') }}
                                    @else
                                        —
                                    @endif
                                </td>

                                <td class="text-end" style="width: 100px;">
                                    @if(!empty($wallet['id']))
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-danger"
                                            onclick="window.location='{{ route('send-form', $wallet['id']) }}'"
                                        >
                                            SEND
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
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
</div>
