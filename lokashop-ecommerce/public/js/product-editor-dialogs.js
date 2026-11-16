(() => {
  let opener = null;
  function open(dialog, button) {
    if (!dialog || dialog.open) return;
    opener = button;
    dialog.showModal();
    const first = dialog.querySelector('input:not([type="hidden"]),select,textarea');
    first?.focus();
    dialog.addEventListener('close', () => {
      dialog.querySelectorAll('video').forEach(video => video.pause());
      if (opener?.isConnected) opener.focus();
      opener = null;
    }, {once: true});
  }
  document.addEventListener('click', event => {
    const button = event.target.closest('[data-product-dialog]');
    if (button) { open(document.getElementById(button.dataset.productDialog), button); return; }
    const closer = event.target.closest('[data-close-product-dialog]');
    if (closer) closer.closest('dialog')?.close();
  });
  const failed = document.querySelector('[data-reopen-product-dialog]');
  if (failed) open(failed, document.querySelector(`[data-product-dialog="${failed.id}"]`));
})();
