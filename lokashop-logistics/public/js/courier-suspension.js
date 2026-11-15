/* Native dialog: focus containment, Escape and accessible labels are browser-managed. */
(() => {
  let opener = null;
  function open(dialog, button) {
    if (!dialog || dialog.open) return;
    opener = button;
    dialog.showModal();
    dialog.querySelector('[name="reason"]')?.focus();
    dialog.addEventListener('close', () => {
      if (opener?.isConnected) opener.focus();
      opener = null;
    }, {once: true});
  }
  document.addEventListener('click', event => {
    const button = event.target.closest('[data-courier-suspend]');
    if (button) { open(document.getElementById(button.dataset.courierSuspend), button); return; }
    const closer = event.target.closest('[data-close-courier-suspend]');
    if (closer) closer.closest('dialog')?.close();
  });
  const failed = document.querySelector('[data-reopen-courier-suspend]');
  if (failed) open(failed, document.querySelector(`[data-courier-suspend="${failed.id}"]`));
})();

/* Invitation form stays mounted so role/vehicle selection and file fields keep their state. */
(() => {
  const dialog = document.getElementById('courier-invite-dialog');
  const button = document.querySelector('[data-open-courier-invite]');
  if (!dialog || !button) return;
  const open = () => { if (!dialog.open) { dialog.showModal(); dialog.querySelector('[name="courier_type"]')?.focus(); } };
  button.addEventListener('click', open);
  dialog.querySelectorAll('[data-close-courier-invite]').forEach(close => close.addEventListener('click', () => dialog.close()));
  dialog.addEventListener('close', () => button.focus());
  if (dialog.hasAttribute('data-reopen-courier-invite')) open();
})();
