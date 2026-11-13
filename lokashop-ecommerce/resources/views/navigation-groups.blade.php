@php
  $groupLayouts = json_decode('{"buyer":{"Shopping":["Shop","Cart","Wishlist"],"Orders":["My orders","Returns"],"Payments & Offers":["Wallet","Vouchers"],"Support":["Reviews","Complaints","Messages"],"Account":["Account","Addresses","Notifications"]},"seller":{"Shop Management":["Products","Orders","Sales","Store","Addresses"],"Marketing":["Promotions","Vouchers"],"Finance":["Returns","Earnings","Wallet"],"Support":["Reviews","Messages"],"Account":["Account"]},"admin":{"User Management":["Approvals","Users","Appeals"],"Marketplace":["Products","Platform vouchers"],"Support":["Complaints","Messages"],"Reports & Finance":["Reports","Wallet","Financial report"],"Communication":["Announcements"],"Account":["Account"]},"logistics_admin":{"Finance":["Wallet","Shipping fee distribution"],"Network Management":["Territories"],"User Management":["Applications","Users"],"Support":["Messages"],"Account":["Account"]},"sorting_center":{"Parcel Operations":["Pickups","Transfers","Deliveries","Parcels","Tracking","Returns"],"Team Management":["Application","Applications","Courier","Appeal"],"Service Setup":["Area","Areas","Rates"],"Finance & Reports":["Wallet","Financial report","COD Collections & Remittances","Filtered report","Reports"],"Support":["Messages"],"Account":["Account"]},"driver":{"Trip Operations":["Trips"],"Work Setup":["Availability","Vehicle"],"Finance":["Financial report","Wallet"],"Support":["Messages"],"Account":["Account"]},"rider":{"Delivery Operations":["Assignments"],"Work Setup":["Availability","Vehicle"],"Finance":["Financial report","Earnings","Wallet","COD Collections & Remittances"],"Support":["Messages"],"Account":["Account"]}}', true);
  $groupRole = auth()->user()->role;
  if ($groupRole === 'rider' && auth()->user()->courier_type === 'driver') $groupRole = 'driver';
  $groupPlan = $groupLayouts[$groupRole] ?? [];
  $groupedLinks = [];
  $remainingLinks = $navLinks;
  unset($remainingLinks['Overview']);
  foreach ($groupPlan as $heading => $labels) {
    $items = [];
    foreach ($labels as $label) {
      if (array_key_exists($label, $remainingLinks)) {
        $items[$label] = $remainingLinks[$label];
        unset($remainingLinks[$label]);
      }
    }
    if ($items) $groupedLinks[$heading] = $items;
  }
  if ($remainingLinks) $groupedLinks['Other Options'] = $remainingLinks;
@endphp
<nav class="grouped-navigation" aria-label="{{ $roleLabel }} navigation">
 @if(isset($navLinks['Overview']))
 <a href="{{ $navLinks['Overview'] }}" class="{{ $active($navLinks['Overview']) ? 'active' : '' }}" @if($active($navLinks['Overview'])) aria-current="page" @endif>{!! $icon('Overview') !!}<span>Overview</span></a>
 @endif
 @foreach($groupedLinks as $heading => $items)
 @php $groupOpen = false; foreach ($items as $itemUrl) { if ($active($itemUrl)) $groupOpen = true; } @endphp
 <details class="nav-group" @if($groupOpen) open @endif>
  <summary><span class="nav-group-mark" aria-hidden="true">{!! $icon(array_key_first($items)) !!}</span><span>{{ $heading }}</span><svg class="nav-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg></summary>
  <div class="nav-group-items">
   @foreach($items as $label => $url)
   <a href="{{ $url }}" class="{{ $active($url) ? 'active' : '' }}" @if($active($url)) aria-current="page" @endif>{!! $icon($label) !!}<span>{{ $label }}</span></a>
   @endforeach
  </div>
 </details>
 @endforeach
</nav>
