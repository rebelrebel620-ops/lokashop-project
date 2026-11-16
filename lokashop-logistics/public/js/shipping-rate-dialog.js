(() => {
 const dialog=document.getElementById('shipping-rate-dialog');
 const form=dialog?.querySelector('form[action="/ops/rate"]');
 if(!dialog||!form)return;
 let opener;
 function open(button){if(!dialog.open){opener=button;dialog.showModal();}}
 dialog.addEventListener('close',()=>{opener?.focus();opener=null;});
 dialog.querySelectorAll('[data-close-rate-popup]').forEach(button=>button.addEventListener('click',()=>dialog.close()));
 document.querySelector('[data-open-rate-popup]')?.addEventListener('click',event=>{
  form.reset();
  for(const name of ['id','origin_province','region','province','city','base_fee','extra_kg_fee','reason']){const input=form.querySelector(`[name="${name}"]`);if(input)input.value='';}
  const region=form.querySelector('[name="region"]');region?.dispatchEvent(new Event('change',{bubbles:true}));
  const active=form.querySelector('[name="active"][type="checkbox"]');if(active)active.checked=true;
  document.getElementById('shipping-rate-title').textContent='Save shipping rate';open(event.currentTarget);
 });
 document.querySelectorAll('.rate-edit').forEach(button=>button.addEventListener('click',()=>{
  const rate=JSON.parse(button.dataset.rate);
  for(const [key,value] of Object.entries(rate)){
   const input=form.querySelector(`[name="${key}"]`+(key==='active'?'[type="checkbox"]':''));if(!input)continue;
   if(input.type==='checkbox')input.checked=Boolean(Number(value));
   else {if(input.tagName==='SELECT'&&value&&!Array.from(input.options).some(option=>option.value===String(value)))input.add(new Option(String(value)+' (saved)',String(value)));input.value=value??'';}
  }
  form.querySelector('[name="reason"]').value='';document.getElementById('shipping-rate-title').textContent='Edit shipping rate';open(button);form.querySelector('[name="reason"]').focus();
 }));
 if(dialog.hasAttribute('data-reopen-rate-popup'))open(document.querySelector('[data-open-rate-popup]'));
})();
