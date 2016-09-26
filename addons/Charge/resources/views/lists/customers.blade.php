@extends('layout')

@section('content')
    @include('Charge::partials.tabs')
    <table class="table">
        <tr>
            <th>Email</th>
            <th>ID</th>
        </tr>
    @foreach ($customers as $customer)
        <tr>
            <td>{{ $customer['email'] }}</td>
            <td>{{ $customer['id'] }}</td>
        </tr>
    @endforeach
    </table>
@endsection