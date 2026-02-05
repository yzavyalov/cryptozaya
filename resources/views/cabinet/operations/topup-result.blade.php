@extends('cabinet.layout.template')

@section('content')
    <div class="container mt-4">
        <h3 class="mb-3">Top-up balance</h3>

        <div class="card shadow-sm">
            <div class="card-body p-4">
                <p>Select address and transfer <strong>{{ $amount }} USDT</strong>.</p>

                <div class="table-responsive mb-4">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Address</th>
                            <th class="text-center">QR Code</th>
                            <th class="text-center">Copy</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($wallets as $item)
                            <tr>
                                <td>
                                    <code id="wallet-{{ $item['wallet']->id }}">{{ $item['wallet']->number }}</code>
                                </td>
                                <td class="text-center">
                                    <div class="d-inline-block" style="width:100px">
                                        {!! $item['qr'] !!}
                                    </div>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-outline-primary btn-sm" onclick="copyToClipboard('wallet-{{ $item['wallet']->id }}')">Copy</button>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function copyToClipboard(id) {
            const text = document.getElementById(id).textContent.trim();
            navigator.clipboard.writeText(text).then(() => {
                alert('Address copied: ' + text);
            });
        }
    </script>
@endsection
