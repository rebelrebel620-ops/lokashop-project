/* Shared, accessible native dialogs for administrator account status forms. */
document.addEventListener('click', function (event) {
  const opener = event.target.closest('[data-account-dialog]');
  if (opener) {
    const dialog = document.getElementById(opener.dataset.accountDialog);
    if (dialog && !dialog.open) {
      dialog.showModal();
      dialog.addEventListener('close', function () { opener.focus(); }, {once: true});
    }
    return;
  }
  const closer = event.target.closest('[data-close-account-dialog]');
  if (closer) closer.closest('dialog')?.close();
});
