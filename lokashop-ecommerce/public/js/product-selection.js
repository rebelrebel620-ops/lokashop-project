(() => {
 const form = document.querySelector('[data-purchase-form]');
 const select = form?.querySelector('[name="variation_id"]');
 const price = document.getElementById('live-product-price');
 const stockLabel = document.querySelector('[data-base-stock]');
 const main = document.getElementById('product-main-image');
 const thumbs = [...document.querySelectorAll('[data-gallery-image]')];
 const baseImage = main?.getAttribute('src') || '';
 const basePrice = Number(price?.dataset.basePrice || 0);
 const baseStock = Number(stockLabel?.dataset.baseStock || 0);
 const format = value => new Intl.NumberFormat('en-PH', {style: 'currency', currency: 'PHP'}).format(value);
 function update() {
  const option = select?.selectedOptions[0];
  const selected = !!select?.value;
  const value = selected ? Number(option.dataset.price) : basePrice;
  const stock = selected ? Math.min(baseStock, Number(option.dataset.stock)) : baseStock;
  const image = selected ? option.dataset.image || baseImage : baseImage;
  if (price) price.textContent = format(value);
  if (stockLabel) stockLabel.textContent = stock > 0 ? stock + ' units available' : 'Out of stock';
  if (main) main.src = image;
  // A variation photo is its own view; base-product thumbnails return on reset.
  thumbs.forEach(button => {
   button.hidden = selected && !!option.dataset.image;
   button.setAttribute('aria-pressed', String(button.dataset.galleryImage === image));
  });
  const oldImage = document.getElementById('variant-image');
  if (oldImage) { oldImage.hidden = true; oldImage.removeAttribute('src'); }
  const info = document.getElementById('selected-variant');
  if (info) info.textContent = selected ? option.textContent.trim() : 'Original product · base price and stock';
  const quantity = form?.querySelector('[name="quantity"]');
  if (quantity) { quantity.max = String(stock); quantity.value = String(Math.max(1, Math.min(stock || 1, Number(quantity.value) || 1))); }
  form?.querySelectorAll('.purchase-actions button').forEach(button => button.disabled = stock <= 0 || form.dataset.vacation === '1');
 }
 select?.addEventListener('change', update);
 thumbs.forEach(button => button.addEventListener('click', () => {
  if (!main || button.hidden) return;
  main.src = button.dataset.galleryImage;
  thumbs.forEach(other => other.setAttribute('aria-pressed', String(other === button)));
 }));
 document.querySelectorAll('[data-quantity-step]').forEach(button => button.addEventListener('click', () => {
  const input = button.parentElement.querySelector('input');
  input.value = String(Math.max(1, Math.min(Number(input.max) || 1, (Number(input.value) || 1) + Number(button.dataset.quantityStep))));
 }));
 update();
})();
