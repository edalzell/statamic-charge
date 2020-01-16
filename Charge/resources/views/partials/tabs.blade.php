<div id="charge" class="tabs">
    <a href="/{{ $charge['addon_cp_route'] }}" class="brand"><em class="icon icon-credit-card"></em> Charge
        {{ $charge['version'] }}</a>
    <a href="/{{ $charge['addon_cp_route'] }}/customers" @if (Request::segment(4)=='customers' )class="active"
        @endif>Customers</a>
    <a href="/{{ $charge['addon_cp_route'] }}/charges" @if (Request::segment(4)=='charges' )class="active"
        @endif>Charges</a>
    <a href="/{{ $charge['addon_cp_route'] }}/subscriptions" @if (Request::segment(4)=='subscriptions' )class="active"
        @endif>Subscriptions</a>

</div>