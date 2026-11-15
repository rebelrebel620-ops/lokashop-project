(() => {
  const sidebar = document.getElementById('logistics-sidebar');
  const toggle = document.querySelector('.phone-sidebar-toggle');
  const close = document.querySelector('.phone-sidebar-close');
  const backdrop = document.querySelector('.phone-sidebar-backdrop');
  if (!sidebar || !toggle || !close || !backdrop) return;
  const phone = window.matchMedia('(max-width:1024px)');
  const focusable = () => [...sidebar.querySelectorAll('a[href],button,summary,input,select,textarea,[tabindex="0"]')]
    .filter(el => !el.disabled && el.getClientRects().length);
  function setOpen(open, restoreFocus = true) {
    open = phone.matches && open;
    document.body.classList.toggle('phone-sidebar-open', open);
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
    backdrop.hidden = !open;
    sidebar.inert = phone.matches && !open;
    if (phone.matches && !open) sidebar.setAttribute('aria-hidden', 'true');
    else sidebar.removeAttribute('aria-hidden');
    if (open) close.focus();
    else if (restoreFocus && phone.matches) toggle.focus();
  }
  toggle.addEventListener('click', () => setOpen(toggle.getAttribute('aria-expanded') !== 'true'));
  close.addEventListener('click', () => setOpen(false));
  backdrop.addEventListener('click', () => setOpen(false));
  sidebar.addEventListener('click', event => {
    if (event.target.closest('a[href]') && phone.matches) setOpen(false, false);
  });
  document.addEventListener('keydown', event => {
    if (!phone.matches || toggle.getAttribute('aria-expanded') !== 'true') return;
    if (event.key === 'Escape') { event.preventDefault(); setOpen(false); }
    if (event.key === 'Tab') {
      const items = focusable(), first = items[0], last = items[items.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
  });
  if (phone.addEventListener) phone.addEventListener('change', () => setOpen(false, false));
  else phone.addListener(() => setOpen(false, false));
  setOpen(false, false);
})();
