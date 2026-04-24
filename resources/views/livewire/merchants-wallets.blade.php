<div>
    <div wire:poll.10s="refreshWallets">
        <h3 class="m-3">My merchants' main and withdraw wallets</h3>

        <div>
            <div class="card shadow-sm p-3 w-100">

                @if (session()->has('success'))
                    <div class="alert alert-success">
                        {{ session('success') }}
                    </div>
                @endif

                <div class="d-flex justify-content-end align-items-center gap-2 mb-3">
                    <button wire:click="toggleCreateWalletForm" type="button" class="btn btn-primary">
                        ➕ Create Wallet
                    </button>

                    <button wire:click="refreshWallets" type="button" class="btn btn-outline-secondary">
                        🔄 Refresh balances
                    </button>
                </div>

                @if($showCreateWalletForm)
                    <div class="border rounded p-3 mb-4 bg-light">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Merchant</label>
                                <select wire:model="selectedMerchantId" class="form-select">
                                    <option value="">Select merchant</option>
                                    @foreach($merchants as $merchant)
                                        <option value="{{ $merchant->id }}">
                                            {{ $merchant->name ?? ('Merchant #' . $merchant->id) }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('selectedMerchantId')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Blockchain network</label>
                                <select wire:model="selectedNetwork" class="form-select">
                                    <option value="">Select network</option>
                                    <option value="tron">Tron</option>
                                    <option value="ethereum">Ethereum</option>
                                </select>
                                @error('selectedNetwork')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Wallet type</label>
                                <select wire:model="selectedWalletType" class="form-select">
                                    <option value="">Select wallet type</option>
                                    <option value="main">Main</option>
                                    <option value="withdraw">Withdraw</option>
                                </select>
                                @error('selectedWalletType')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <button wire:click="toggleCreateWalletForm" type="button" class="btn btn-outline-secondary">
                                Cancel
                            </button>

                            <button wire:click="createWallet" type="button" class="btn btn-success">
                                Create
                            </button>
                        </div>
                    </div>
                @endif

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
                                    @if(($wallet['status'] ?? null) == 3)
                                        <span class="badge bg-warning text-dark px-3 py-2">
                                            💸 Withdraw
                                        </span>
                                    @elseif(($wallet['status'] ?? null) == 2)
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
                                            onclick="window.location='{{ route('send-form', ['walletType' => 'merchant_wallet','walletId' => $wallet['id']]) }}'"
                                        >
                                            SEND
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
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
