(() => {
  const row = document.getElementById('trending-products');
  const controls = document.querySelector('[data-product-row-controls]');
  if (!row || !controls) return;
  const previous = controls.querySelector('[data-product-row-prev]');
  const next = controls.querySelector('[data-product-row-next]');
  const update = () => {
    const max = row.scrollWidth - row.clientWidth;
    controls.hidden = max <= 2;
    previous.disabled = row.scrollLeft <= 2;
    next.disabled = row.scrollLeft >= max - 2;
  };
  const move = (direction) => row.scrollBy({
    left: direction * Math.max(200, row.clientWidth * 0.85),
    behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
  });
  previous.addEventListener('click', () => move(-1));
  next.addEventListener('click', () => move(1));
  row.addEventListener('scroll', update, {passive: true});
  row.addEventListener('load', update, true);
  window.addEventListener('resize', update);
  if ('ResizeObserver' in window) new ResizeObserver(update).observe(row);
  update();
})();
