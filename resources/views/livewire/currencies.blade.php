<div class="container mt-4">

    <!-- ADD CURRENCY -->
    <div class="row mb-4 justify-content-center">
        <div class="col-12 col-md-6 col-lg-5">

            <form wire:submit.prevent="create" class="border rounded p-3 bg-white">
                @csrf

                <div class="mb-2">
                    <label for="name" class="form-label small text-muted">
                        New currency
                    </label>
                    <input
                        type="text"
                        id="name"
                        wire:model.defer="name"
                        class="form-control"
                        placeholder="USDT"
                    >
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary btn-sm">
                        Add
                    </button>
                </div>
            </form>

        </div>
    </div>

    <!-- CURRENCIES -->
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">

            <table class="table table-sm table-borderless">
                <tbody>
                @forelse($currencies as $currency)
                    <tr>
                        <td class="fw-medium">
                            {{ $currency->name }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="text-muted text-center">
                            No currencies
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>

        </div>
    </div>

</div>
