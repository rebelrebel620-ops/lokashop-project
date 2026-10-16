document.addEventListener('DOMContentLoaded',()=>{
 document.querySelectorAll('form[method=post]').forEach(f=>{if(!f.querySelector('[name=_request_key]')){const key=document.createElement('input');key.type='hidden';key.name='_request_key';key.value=crypto.randomUUID();f.append(key);}});
 const dialog=document.getElementById('logout-confirm');let logoutForm=null;
 document.querySelectorAll('form[action="/logout"]').forEach(f=>f.addEventListener('submit',e=>{e.preventDefault();e.stopImmediatePropagation();logoutForm=f;dialog.showModal();},true));
 document.getElementById('logout-yes')?.addEventListener('click',()=>logoutForm?.submit());
 document.getElementById('logout-no')?.addEventListener('click',()=>{dialog.close();logoutForm=null;});
 document.querySelectorAll('select[name=courier_type]').forEach(type=>{const vehicle=type.form.querySelector('select[name=vehicle]');if(!vehicle)return;const update=()=>{[...vehicle.options].forEach(o=>o.hidden=type.value==='driver'?['motorcycle','tricycle'].includes(o.value):!['motorcycle','tricycle'].includes(o.value));if(vehicle.selectedOptions[0]?.hidden)vehicle.value=[...vehicle.options].find(o=>!o.hidden)?.value||'';};type.addEventListener('change',update);update();});
 const normalize=s=>s.toLocaleLowerCase().trim();
 let placePromise;
 const places=()=>placePromise??=fetch('/data/places.json').then(r=>{if(!r.ok)throw Error('Address list could not load.');return r.json();});
 document.querySelectorAll('form').forEach(async form=>{
  const fields=['region','province','city','barangay'];
  const existing=Object.fromEntries(fields.map(n=>[n,form.querySelector(`[name="${n}"]`)]));
  if(!existing.province||!existing.city)return;
  // A compact registration/pickup form may omit region; add it above province for cascading selection.
  if(!existing.region){const label=document.createElement('label');label.textContent='Region';const input=document.createElement('input');input.name='region';label.append(input);(existing.province.closest('label')||existing.province.parentElement).before(label);existing.region=input;}
  const selected={};const controls={};
  fields.forEach(n=>{const old=existing[n];if(!old)return;selected[n]=old.value;const s=document.createElement('select');s.name=n;s.required=old.required;s.className=old.className;s.disabled=true;old.replaceWith(s);controls[n]=s;});
  const fill=(n,rows,restore=false)=>{const s=controls[n];if(!s)return;s.replaceChildren(new Option('Choose '+(n==='city'?'city / municipality':n),''));rows.sort((a,b)=>a.name.localeCompare(b.name)).forEach(x=>{const o=new Option(x.name,x.name);o.dataset.code=x.code;s.append(o);});s.disabled=false;if(restore&&selected[n]){let o=[...s.options].find(o=>normalize(o.value)===normalize(selected[n]));if(!o){o=new Option(selected[n]+' (saved address)',selected[n]);s.append(o);}s.value=selected[n];}};
  const code=n=>controls[n]?.selectedOptions[0]?.dataset.code;
  try{const d=await places();
   const provinces=restore=>fill('province',d.provinces.filter(x=>x.regionCode===code('region')),restore);
   const cities=restore=>fill('city',d['cities-municipalities'].filter(x=>x.provinceCode===code('province')),restore);
   const barangays=restore=>fill('barangay',d.barangays.filter(x=>(x.cityCode||x.municipalityCode)===code('city')),restore);
   fill('region',d.regions,true);
   if(!code('region')&&selected.province){const p=d.provinces.find(x=>normalize(x.name)===normalize(selected.province));const r=p&&d.regions.find(x=>x.code===p.regionCode);if(r)controls.region.value=r.name;}
   provinces(true);cities(true);barangays(true);
   controls.region.addEventListener('change',()=>{provinces(false);cities(false);barangays(false);});
   controls.province.addEventListener('change',()=>{cities(false);barangays(false);});
   controls.city.addEventListener('change',()=>barangays(false));
  }catch(e){const p=document.createElement('p');p.textContent='The address list could not load. Refresh the page to try again.';p.setAttribute('role','alert');form.prepend(p);}
 });
});