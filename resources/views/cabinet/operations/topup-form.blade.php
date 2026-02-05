@extends('cabinet.layout.template')

@section('content')
    <div class="container mt-4">
        <h3 class="mb-3">Top-up balance</h3>

        <div class="card shadow-sm">
            <div class="card-body p-0">
                <form action="{{ route('top-up-balance') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label for="blockchain" class="form-label">Select blockchain</label>
                        <select name="blockchain" id="blockchain" class="form-select">
                            @foreach(\App\Http\Enums\BlockChainEnum::cases() as $chain)
                                <option value="{{ $chain->value }}">
                                    {{ $chain->label() }}
                                </option>
                            @endforeach
                        </select>
                        <label for="amount" class="form-label">Amount</label>
                        <input type="text" name="amount" id="amount">
                    </div>
                    <div>
                        <button type="submit">Submit</button>
                    </div>
                </form>
                <button onclick="window.location='{{ route('check-transactions') }}'">Check transactions</button>
            </div>
        </div>
    </div>

@endsection

