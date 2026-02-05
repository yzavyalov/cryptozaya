<div>
    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h5 class="mb-0">All Merchants</h5>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 align-middle">
                    <thead class="table-light">
                    <tr>
                        <th class="text-nowrap">ID</th>
                        <th class="text-nowrap">Name</th>
                        <th class="text-nowrap">Status</th>
                        <th class="text-nowrap">Token</th>
                        <th class="text-nowrap">Created At</th>
                        <th class="text-nowrap"></th>
                        <th class="text-nowrap"></th>
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
                                @else
                                    <span class="text-danger fw-bold">NULL</span>
                                @endif
                            </td>

                            <td class="text-nowrap">{{ $merchant->created_at }}</td>
                            <td class="text-nowrap">
                                @if($merchant->status !== \App\Http\Enums\MerchantStatusEnum::BLOCKED->value)
                                    <button wire:click="blockMerchant({{ $merchant->id }})" class="btn btn-sm btn-danger">
                                        Block
                                    </button>
                                @else
                                    <button wire:click="blockMerchant({{ $merchant->id }})" class="btn btn-sm btn-outline-danger">
                                        Unblock
                                    </button>
                                @endif

                            </td>
                            <td class="text-nowrap">
                                @if($merchant->status !== \App\Http\Enums\MerchantStatusEnum::PAID->value)
                                    <button wire:click="paidStatus({{ $merchant->id }})" class="btn btn-sm btn-success">
                                        Paid
                                    </button>
                                @else
                                    <button wire:click="paidStatus({{ $merchant->id }})" class="btn btn-sm btn-outline-success">
                                        Unpaid
                                    </button>
                                @endif

                            </td>

                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
