@php
/* Usage: <x-badge :status="$p->status" />
   Maps raw workflow / assignment status strings to a colour + readable label. */
$s = strtoupper((string)($status ?? ''));
$map = [
  'PLACED'=>'gray','CONFIRMED'=>'blue','PREPARING'=>'amber','READY_FOR_PICKUP'=>'amber',
  'PICKED_UP'=>'purple','AT_SORTING_CENTER'=>'green','SORTED'=>'blue','ASSIGNED_TO_RIDER'=>'blue',
  'OUT_FOR_DELIVERY'=>'teal','DELIVERED'=>'green','COMPLETED'=>'green','DELIVERY_FAILED'=>'red',
  'RETURNED'=>'gray','REQUESTED'=>'amber','OFFERED'=>'amber','ACCEPTED'=>'blue','FAILED'=>'red',
  'PENDING'=>'amber','APPROVED'=>'green','REJECTED'=>'red',
];
$color = $map[$s] ?? 'gray';
$label = ucwords(strtolower(str_replace('_',' ',$s)));
@endphp
<span class="badge {{ $color }}">{{ $label }}</span>
