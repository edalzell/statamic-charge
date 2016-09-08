@extends('layout')

@section('content')
    <table class="table">
        <tr>
            <th>Date</th>
            <th>Email</th>
            <th>Description</th>
            <th>Amount</th>
        </tr>
        @foreach ($charges as $charge)
            <tr>
                <td>{!! \Statamic\Addons\Charge\Charge::getLocalDateTimeFromUTC($charge['created'])->format('M j, Y - g:iA') !!}</td>
                <td>{{ $charge['receipt_email'] }}</td>
                <td>{{ $charge['description'] }}</td>
                <td>${{ number_format($charge['amount'] / 100, 2) }}</td>
            </tr>
        @endforeach
    </table>
@endsection