@extends('cabinet.layout.template')

@section('content')
    <div class="container mt-4">
        @livewire('send-money', [
            'walletType' => $walletType,
            'walletId' => $walletId
        ])
    </div>
@endsection
