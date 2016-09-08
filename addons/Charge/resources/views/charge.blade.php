@extends('layout')

@section('content')
    <table class="table">
        <tr>
            <th>Date</th>
            <th>Email</th>
            <th>Description</th>
            <th>Amount</th>
            <th>Stripe</th>
        </tr>
        @foreach ($charges as $charge)
            <tr>
                <td>{!! \Statamic\Addons\Charge\Charge::getLocalDateTimeFromUTC($charge['created'])->format('M j, Y - g:iA') !!}</td>
                <td>{{ $charge['receipt_email'] }}</td>
                <td>{{ $charge['description'] }}</td>
                <td>${{ number_format($charge['amount'] / 100, 2) }}</td>
                <td><a href="https://dashboard.stripe.com/payments/{{ $charge['id'] }}">Transaction</a></td>
            </tr>
        @endforeach
    </table>
@endsection