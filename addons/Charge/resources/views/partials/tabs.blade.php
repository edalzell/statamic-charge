
<div id="charge" class="tabs">
    <a href="/{{ $charge['cp_path'] }}/addons/{{ strtolower($charge['addon_name']) }}" class="brand"><em class="icon icon-credit-card"></em> Charge {{ $charge['version'] }}</a>
    <a href="/{{ $charge['cp_path'] }}/addons/{{ strtolower($charge['addon_name']) }}/customers" @if (Request::segment(4)=='customers')class="active" @endif>Customers</a>
    <a href="/{{ $charge['cp_path'] }}/addons/{{ strtolower($charge['addon_name']) }}/charges" @if (Request::segment(4)=='charges')class="active" @endif>Charges</a>
    <a href="/{{ $charge['cp_path'] }}/addons/{{ strtolower($charge['addon_name']) }}/subscriptions" @if (Request::segment(4)=='subscriptions')class="active" @endif>Subscriptions</a>

</div>