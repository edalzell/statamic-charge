@extends('layout')

@section('content')
@include('Charge::partials.tabs')
<table class="table">
    <tr>
        <th>Email</th>
        <th>Plan</th>
        <th>Amount</th>
        <th>Auto-Renew?</th>
        <th>Expiry</th>
        <th>Action</th>
    </tr>
    @foreach ($subscriptions as $subscription)
    <tr>
        <td>{{ $subscription['email'] }}</td>
        <td>{{ $subscription['plan'] }}</td>
        <td>${{ number_format($subscription['amount'] / 100, 2) }}</td>
        <td>{{ $subscription['auto_renew'] ? 'Yes' : 'No' }}</td>
        <td>{{ date('M j, Y', $subscription['expiry_date']) }}</td>
        <td>
            <form method="POST" action="subscription/{{ $subscription['id'] }}">
                {{ csrf_field() }}
                {{ method_field('DELETE') }}
                <button type="submit">Cancel</button>
            </form>
        </td>
    </tr>
    @endforeach
</table>
@endsection