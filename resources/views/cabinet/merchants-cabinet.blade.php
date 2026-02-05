@extends('cabinet.layout.template')

@section('content')
    <div>
        Name: {{ $merchant->name }}
        Administrators:
        @foreach($merchant->users as $user)
            name: {{ $user->name }}
            email: {{ $user->email }}
        @endforeach

    </div>
@endsection
