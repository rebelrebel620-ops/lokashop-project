(() => {
 const form = document.querySelector('[data-purchase-form]');
 const select = form?.querySelector('[name="variation_id"]');
 const price = document.getElementById('live-product-price');
 const stockLabel = document.querySelector('[data-base-stock]');
 const main = document.getElementById('product-main-image');
 const thumbs = [...document.querySelectorAll('[data-gallery-image]')];
 const baseImage = main?.getAttribute('src') || '';
 let basePrice = Number(price?.dataset.basePrice || 0);
 let baseStock = Number(stockLabel?.dataset.baseStock || 0);
 const format = value => new Intl.NumberFormat('en-PH', {style: 'currency', currency: 'PHP'}).format(value);
 let available = true;
 const setText=(node,text)=>{if(node&&node.textContent!==text)node.textContent=text;};
 function update(media = true) {
  const option = select?.selectedOptions[0];
  const selected = !!select?.value;
  const value = selected ? Number(option.dataset.price) : basePrice;
  const stock = selected ? Math.min(baseStock, Number(option.dataset.stock)) : baseStock;
  const image = selected ? option.dataset.image || baseImage : baseImage;
  setText(price,format(value));
  setText(stockLabel,available?(stock > 0 ? stock + ' units available' : 'Out of stock'):'Product currently unavailable');
  if (media) {
  if (main) main.src = image;
  // A variation photo is its own view; base-product thumbnails return on reset.
  thumbs.forEach(button => {
   button.hidden = selected && !!option.dataset.image;
   button.setAttribute('aria-pressed', String(button.dataset.galleryImage === image));
  });
  }
  const oldImage = document.getElementById('variant-image');
  if (oldImage) { oldImage.hidden = true; oldImage.removeAttribute('src'); }
  const info = document.getElementById('selected-variant');
  if (info) info.textContent = selected ? option.textContent.trim() : 'Original product · base price and stock';
  const quantity = form?.querySelector('[name="quantity"]');
  if (quantity) { quantity.max = String(stock); if(document.activeElement!==quantity)quantity.value = String(Math.max(1, Math.min(stock || 1, Number(quantity.value) || 1))); }
  form?.querySelectorAll('.purchase-actions button').forEach(button => button.disabled = !available || stock <= 0 || !!option?.disabled || form.dataset.vacation === '1');
 }
 select?.addEventListener('change', () => update(true));
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
 const liveUrl=document.querySelector('[data-product-live-url]')?.dataset.productLiveUrl;
 let timer,stopped=false,busy=false;
 async function poll(){
  if(stopped||busy)return;
  if(document.hidden||navigator.onLine===false){schedule();return;}
  busy=true;const controller=new AbortController();const timeout=setTimeout(()=>controller.abort(),8000);
  try{
   const response=await fetch(liveUrl,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'},signal:controller.signal});
   if(!response.ok)return;const state=await response.json();available=state.available===true;
   if(available){
    basePrice=Number(state.price);baseStock=Number(state.stock);
    if(price)price.dataset.basePrice=String(basePrice);if(stockLabel)stockLabel.dataset.baseStock=String(baseStock);
    if(form)form.dataset.vacation=state.vacation?'1':'0';
    if(select){
     const current=select.value;const ids=new Set(state.variations.map(v=>String(v.id)));
     [...select.options].filter(o=>o.value&&!ids.has(o.value)).forEach(o=>{if(o.value===current){o.disabled=true;o.dataset.stock='0';}else o.remove();});
     for(const variant of state.variations){let option=[...select.options].find(o=>o.value===String(variant.id));if(!option){option=new Option('',String(variant.id));select.add(option);}option.dataset.price=String(variant.price);option.dataset.stock=String(variant.stock);option.dataset.image=variant.image||'';option.disabled=variant.stock<=0;setText(option,variant.label);}
     select.value=current;
    }
   }
   // Price, stock and purchase availability only: no photo/video DOM, source or playback changes.
   update(false);
  }catch(error){/* Keep the user's current view during transient network failures. */}
  finally{clearTimeout(timeout);busy=false;schedule();}
 }
 function schedule(){clearTimeout(timer);if(!stopped&&liveUrl)timer=setTimeout(poll,10000);}
 document.addEventListener('visibilitychange',()=>{if(!document.hidden&&liveUrl)poll();});
 window.addEventListener('pagehide',()=>{stopped=true;clearTimeout(timer);});
 window.addEventListener('pageshow',event=>{if(event.persisted){stopped=false;schedule();}});
 schedule();
})();
