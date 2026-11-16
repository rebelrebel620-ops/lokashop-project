(() => {
  const dialog = document.getElementById('bulk-trip-dialog');
  const form = document.getElementById('bulk-trip-form');
  const opener = document.querySelector('[data-open-bulk-trip]');
  if (!dialog || !form || !opener) return;
  const submit = document.getElementById('bulk-trip-submit');
  function updateSelection() {
    const selected = [...form.querySelectorAll('[name="parcel_ids[]"]:checked')];
    const stops = new Set(selected.map(box => box.dataset.destination));
    const invalid = selected.length > 100 || stops.size > 10;
    document.getElementById('bulk-selection-count').textContent = `${selected.length} parcels · ${stops.size} destination centers selected${invalid ? ' — maximum 100 parcels and 10 centers' : ''}`;
    submit.disabled = !selected.length || invalid || !form.querySelector('[name="driver_id"]').value;
  }
  function open() { if (!dialog.open) { updateSelection(); dialog.showModal(); form.querySelector('[name="driver_id"]')?.focus(); } }
  opener.addEventListener('click', open);
  dialog.querySelectorAll('[data-close-bulk-trip]').forEach(button => button.addEventListener('click', () => dialog.close()));
  dialog.addEventListener('close', () => opener.focus());
  form.addEventListener('change', updateSelection);
  form.addEventListener('submit', event => { updateSelection(); if (submit.disabled) event.preventDefault(); });
  updateSelection();
  if (dialog.hasAttribute('data-reopen-bulk-trip')) open();
})();
