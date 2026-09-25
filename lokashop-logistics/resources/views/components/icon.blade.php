@php
/* Tiny inline icon set — no external assets/fonts needed.
   Usage: <x-icon name="box" size="20" /> */
$size = $size ?? 20;
$paths = [
  'home'        => '<path d="M4 11.5 12 4l8 7.5"/><path d="M6 10v9h12v-9"/><path d="M10 19v-5h4v5"/>',
  'clipboard'   => '<rect x="6" y="4" width="12" height="17" rx="2"/><rect x="9" y="2.5" width="6" height="3" rx="1"/><path d="M9 11h6M9 15h6"/>',
  'scan'        => '<rect x="5" y="5" width="14" height="14" rx="2"/><path d="M5 9V5h4M19 9V5h-4M5 15v4h4M19 15v4h-4"/>',
  'box'         => '<path d="M4 8 12 4l8 4-8 4-8-4Z"/><path d="M4 8v9l8 4 8-4V8"/><path d="M12 12v9"/>',
  'user'        => '<circle cx="12" cy="8" r="3.4"/><path d="M5 20c0-3.6 3.1-6 7-6s7 2.4 7 6"/>',
  'users'       => '<circle cx="9" cy="8" r="3"/><path d="M3.5 19c0-3 2.6-5 5.5-5s5.5 2 5.5 5"/><circle cx="17" cy="9" r="2.4"/><path d="M15.5 14c2.6.3 4.5 2 4.5 5"/>',
  'map-pin'     => '<path d="M12 21s7-6.1 7-11.5a7 7 0 1 0-14 0C5 14.9 12 21 12 21Z"/><circle cx="12" cy="9.3" r="2.4"/>',
  'truck'       => '<rect x="2.5" y="7" width="12" height="10" rx="1.4"/><path d="M14.5 10.5h3.7L20.5 14v3h-6"/><circle cx="6.3" cy="18.3" r="1.7"/><circle cx="16.7" cy="18.3" r="1.7"/>',
  'chat'        => '<path d="M4 5h16v11H9l-4 4V5Z"/>',
  'gear'        => '<circle cx="12" cy="12" r="3"/><path d="M12 3.5v2.4M12 18.1v2.4M4.6 7.3l2 1.2M17.4 15.5l2 1.2M4.6 16.7l2-1.2M17.4 8.5l2-1.2M3.5 12h2.4M18.1 12h2.4"/>',
  'chart'       => '<path d="M4 20V4M4 20h16"/><rect x="7" y="12" width="3" height="6"/><rect x="12" y="8" width="3" height="10"/><rect x="17" y="5" width="3" height="13"/>',
  'wallet'      => '<rect x="3" y="6.5" width="18" height="12" rx="2.2"/><path d="M3 10h18"/><circle cx="16.5" cy="14.5" r="1.2"/>',
  'bell'        => '<path d="M6 10.5a6 6 0 1 1 12 0c0 3 1 4.5 2 5.5H4c1-1 2-2.5 2-5.5Z"/><path d="M9.5 19a2.5 2.5 0 0 0 5 0"/>',
  'search'      => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="M20 20l-4.6-4.6"/>',
  'check'       => '<path d="M4.5 12.5 9 17l10.5-10.5"/>',
  'check-circle'=> '<circle cx="12" cy="12" r="8.5"/><path d="M8.3 12.3 11 15l5-5.6"/>',
  'x-circle'    => '<circle cx="12" cy="12" r="8.5"/><path d="M9 9l6 6M15 9l-6 6"/>',
  'arrow-right' => '<path d="M4 12h15M13 6l6 6-6 6"/>',
  'route'       => '<circle cx="5.5" cy="6" r="2"/><circle cx="18.5" cy="18" r="2"/><path d="M5.5 8v3a4 4 0 0 0 4 4h5"/>',
  'send'        => '<path d="M4 11 20 4l-6.5 16-3-6-6.5-3Z"/>',
  'plus'        => '<path d="M12 4.5v15M4.5 12h15"/>',
  'logout'      => '<path d="M9 4H5.5A1.5 1.5 0 0 0 4 5.5v13A1.5 1.5 0 0 0 5.5 20H9"/><path d="M14 8l5 4-5 4M9 12h10"/>',
  'building'    => '<rect x="4" y="3" width="10" height="18" rx="1"/><path d="M14 8h6v13h-6M7 7h4M7 11h4M7 15h4"/>',
  'money'       => '<circle cx="12" cy="12" r="8.5"/><path d="M9.5 15.2c.4.9 1.3 1.3 2.5 1.3 1.6 0 2.6-.8 2.6-1.9 0-1-.8-1.5-2.6-1.9-1.7-.4-2.3-.9-2.3-1.8 0-1 1-1.7 2.4-1.7 1.1 0 2 .4 2.4 1.2"/><path d="M12 7v10"/>',
];
$path = $paths[$name] ?? $paths['box'];
@endphp
<svg xmlns="http://www.w3.org/2000/svg" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24"
     fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
     {{ $attributes }}>{!! $path !!}</svg>
