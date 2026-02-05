<div>
    <h3 class="m-3">My Merchants</h3>

    <div class="card shadow-sm p-3 w-100">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 align-middle">
                    <thead class="table-light">
                    <tr>
                        <th class="text-nowrap">ID</th>
                        <th class="text-nowrap">Name</th>
                        <th class="text-nowrap">Status</th>
                        <th class="text-nowrap">Token</th>
                        <th class="text-nowrap">Callback url</th>
                        <th class="text-nowrap">May main wallet</th>
                        <th class="text-nowrap">May withdraw wallet</th>
                        <th class="text-nowrap">Created At</th>
                    </tr>
                    </thead>

                    <tbody>
                    @foreach($merchants as $merchant)
                        <tr>
                            <td>{{ $merchant->id }}</td>
                            <td>{{ $merchant->name }}</td>

                            <td>
                                <span class="badge bg-info">
                                    {{ \App\Http\Enums\MerchantStatusEnum::from($merchant->status)->label() }}
                                </span>
                            </td>

                            <td>
                                @if($merchant->token)
                                    <span class="text-success fw-bold">Saved</span>
                                    <br>
                                    <button wire:click="updateToken({{ $merchant->id }})"
                                            class="btn btn-sm btn-warning mt-1">
                                        Delete old & create new
                                    </button>

                                @elseif(isset($generatedTokens[$merchant->id]))
                                    <div class="d-flex flex-column">
                                        <span class="fw-monospace mb-1">{{ $generatedTokens[$merchant->id] }}</span>

                                        <button wire:click="storeToken({{ $merchant->id }})"
                                                class="btn btn-sm btn-success">
                                            Save & Copy Token
                                        </button>
                                    </div>

                                @else
                                    <button wire:click="createToken({{ $merchant->id }})"
                                            class="btn btn-sm btn-primary">
                                        Create Token
                                    </button>
                                @endif
                            </td>

                            <td class="text-nowrap"
                                x-data="{ editing: false }"
                            >
                                @if($merchant->cburl)
                                    {{ $merchant->cburl }}
                                @else
                                    <template x-if="!editing">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-primary"
                                            @click="editing = true"
                                        >
                                            ADD callback url
                                        </button>
                                    </template>

                                    <template x-if="editing">
                                        <div class="d-flex gap-2">
                                            <input
                                                type="text"
                                                class="form-control form-control-sm"
                                                placeholder="https://example.com/callback"
                                                wire:model.defer="cburls.{{ $merchant->id }}"
                                            >

                                            <button
                                                type="button"
                                                class="btn btn-sm btn-success"
                                                wire:click="saveCburl({{ $merchant->id }})"
                                                @click="editing = false"
                                            >
                                                Save
                                            </button>
                                        </div>
                                    </template>
                                @endif
                            </td>

                            <td class="text-nowrap">
                                @if($merchant->mainWallet())
                                    {{ $merchant->mainWallet()->number }}
                                @else
                                    <button wire:click="createMainWallet({{ $merchant->id }})" class="btn btn-sm btn-success">CREATE MAIN WALLET</button>
                                @endif
                            </td>
                            <td class="text-nowrap">
                                @if($merchant->withDrawWallet())
                                    {{ $merchant->withDrawWallet()->number }}
                                @else
                                    <button wire:click="createWithdrawWallet({{ $merchant->id }})" class="btn btn-sm btn-success">CREATE WITHDRAW WALLET</button>
                                @endif
                            </td>
                            <td class="text-nowrap">{{ $merchant->created_at }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script>
    window.addEventListener('copy-token', event => {
        const token = event.detail.token;

        navigator.clipboard.writeText(token).then(() => {
            alert('Token copied to clipboard: ' + token);
        }).catch(err => {
            console.error('Failed to copy token: ', err);
        });
    });
</script>

