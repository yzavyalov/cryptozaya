@extends('cabinet.layout.template')

@section('content')
    <div>
        <table>
            <thead>
                <th>id</th>
                <th>name</th>
                <th>email</th>
            </thead>
            <tbody>
                @foreach($users as $user)
                    <tr>
                        <td>{{ $user->id }}</td>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

    </div>
@endsection
