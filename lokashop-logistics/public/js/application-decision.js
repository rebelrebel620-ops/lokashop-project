(() => {
  let opener;
  function open(dialog, button) {
    if (!dialog || dialog.open) return;
    opener = button; dialog.showModal(); dialog.querySelector('textarea')?.focus();
    dialog.addEventListener('close', () => { opener?.focus(); opener = null; }, {once: true});
  }
  document.addEventListener('click', event => {
    const button = event.target.closest('[data-application-dialog]');
    if (button) { open(document.getElementById(button.dataset.applicationDialog), button); return; }
    const close = event.target.closest('[data-close-application-dialog]');
    if (close) { close.closest('dialog')?.close(); return; }
    const decision = event.target.closest('.approval-choice-dialog button[name="decision"]');
    if (decision) { const note = decision.form.querySelector('[name="reason"]'); if (note) note.required = decision.value === 'rejected'; }
  });
  const failed = document.querySelector('[data-reopen-application-dialog]');
  if (failed) open(failed, document.querySelector(`[data-application-dialog="${failed.id}"]`));
})();
