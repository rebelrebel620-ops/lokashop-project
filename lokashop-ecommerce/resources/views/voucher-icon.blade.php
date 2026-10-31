<svg viewBox="0 0 100 90" aria-hidden="true" focusable="false" class="voucher-icon">

@if($kind==='shipping')
<path d="M18 27h43v37H18zM61 40h15l14 15v9H61z" fill="#ecfff0"/><path d="M66 43h9l9 10H66z" fill="#52b775"/><circle cx="32" cy="68" r="10" fill="#b8f0c5" stroke="#155b30" stroke-width="4"/><circle cx="77" cy="68" r="10" fill="#b8f0c5" stroke="#155b30" stroke-width="4"/><path d="M7 34h29M3 44h26M10 54h17" stroke="#b8f0c5" stroke-width="6" stroke-linecap="round"/>

@elseif($kind==='store')
<path d="M23 37h56v42H23z" fill="#eaffed"/><path d="M19 23h64l7 19H12z" fill="#8ce365"/><path d="M27 23h12l-4 19H21zM51 23h12l4 19H49zM75 23h8l7 19H77z" fill="#27983c"/><path d="M13 42h77v6c-6 8-11 8-16 0-6 8-12 8-18 0-6 8-12 8-18 0-6 8-12 8-18 0-3 3-5 4-7 3z" fill="#c3f4ad"/><path d="M34 57h15v22H34zM59 55h13v13H59z" fill="#2b9c48"/>

@else
<path d="M32 31v-9c0-24 38-24 38 0v9" fill="none" stroke="#dfffe7" stroke-width="9"/><path d="M21 29h58l8 49H14z" fill="#ecfff0"/><path d="M48 38h15L53 61h16L39 73l4-21z" fill="#139641"/><path d="M8 40h15M3 50h17M7 60h13" stroke="#b7f6c7" stroke-width="5" stroke-linecap="round"/>

@endif
</svg>
