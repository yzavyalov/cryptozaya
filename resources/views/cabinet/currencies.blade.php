@extends('cabinet.layout.template')

@section('content')
    <div>
        <div class="card shadow-sm border rounded-4" style="width: 480px; background-color: #fff;">
           @livewire('currencies')
        </div>
    </div>
@endsection
