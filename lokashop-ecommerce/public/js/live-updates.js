/* LokaShop: refresh changed pages without interrupting unfinished work. */
(() => {
'use strict';
const script=document.getElementById('lokashop-live-script');
if(!script)return;
// Product browsing must never be interrupted by catalog/order polling.
// Product price/stock have a dedicated in-place poller; never reload its gallery.
if(script.dataset.refreshMode==='product')return;
const manualRefresh=script.dataset.refreshMode==='manual';
const url=new URL(location.href);url.searchParams.delete('_live_refresh');
history.replaceState(history.state,'',url);
const scrollKey='lokashop-live-scroll:'+url.pathname+url.search;
try{const saved=JSON.parse(sessionStorage.getItem(scrollKey));sessionStorage.removeItem(scrollKey);if(saved)requestAnimationFrame(()=>window.scrollTo(saved.x,saved.y));}catch(e){}
const originals=new WeakMap();
const value=e=>e.type==='checkbox'||e.type==='radio'?String(e.checked):e.isContentEditable?e.textContent:e.value;
const controls=()=>Array.from(document.querySelectorAll('input,textarea,select,[contenteditable="true"]')).filter(e=>!['hidden','submit','button'].includes(e.type));
controls().forEach(e=>originals.set(e,value(e)));
let pending=false,stopped=false,busy=false,lastInteraction=Date.now(),delay=10000,timer,badge;
function unsafe(){
 if(document.hidden||navigator.onLine===false||Date.now()-lastInteraction<2000)return true;
 if(document.querySelector('dialog[open],video[srcObject]')||Array.from(document.querySelectorAll('video')).some(v=>v.srcObject))return true;
 if(document.activeElement&&document.activeElement.matches('input,textarea,select,[contenteditable="true"]'))return true;
 return controls().some(e=>e.type==='file'?e.files&&e.files.length:originals.has(e)&&originals.get(e)!==value(e));
}
function refresh(){
 stopped=true;clearTimeout(timer);
 try{sessionStorage.setItem(scrollKey,JSON.stringify({x:scrollX,y:scrollY}));}catch(e){}
 const next=new URL(location.href);next.searchParams.set('_live_refresh','1');location.replace(next.href);
}
function show(){
 if(badge)return;
 badge=document.createElement('div');badge.setAttribute('role','status');
 badge.style.cssText='position:fixed;bottom:16px;right:16px;z-index:99999;background:#103f2d;color:white;padding:12px 16px;border-radius:12px;box-shadow:0 4px 20px #0003;font:14px system-ui;max-width:340px';
 badge.append(document.createTextNode(manualRefresh?'Product updates available. Update when you are ready. ':'Updates available. Finish editing to update automatically. '));
 const button=document.createElement('button');button.type='button';button.textContent='Update now';button.style.cssText='margin-left:8px;padding:6px;border-radius:6px;cursor:pointer';
 button.onclick=()=>{if(!unsafe()||confirm('Update this page? Any unfinished edits or selected uploads will be cleared.'))refresh();};badge.append(button);document.body.append(badge);
}
function apply(){if(pending&&!stopped){if(manualRefresh||unsafe())show();else refresh();}}
function schedule(){clearTimeout(timer);if(!stopped)timer=setTimeout(poll,delay);}
async function poll(){
 if(stopped||busy)return;
 if(document.hidden||navigator.onLine===false){schedule();return;}
 busy=true;const controller=new AbortController();const timeout=setTimeout(()=>controller.abort(),10000);
 try{
  const endpoint=new URL(script.dataset.endpoint,location.origin);endpoint.searchParams.set('page',url.pathname+url.search);
  const response=await fetch(endpoint,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'},signal:controller.signal});
  if(response.status===401){pending=true;}else{
   if(!response.ok)throw Error('Live update unavailable');const state=await response.json();
   if(state.revision!==script.dataset.revision||String(Number(state.authenticated))!==script.dataset.authenticated)pending=true;
  }
  delay=10000;apply();
 }catch(e){delay=Math.min(delay*2,60000);}finally{clearTimeout(timeout);busy=false;schedule();}
}
['pointerdown','keydown','input','change'].forEach(name=>document.addEventListener(name,()=>{lastInteraction=Date.now();setTimeout(apply,2100);},true));
document.addEventListener('submit',()=>{stopped=true;clearTimeout(timer);},true);
window.addEventListener('pagehide',()=>{stopped=true;clearTimeout(timer);});
window.addEventListener('pageshow',event=>{if(event.persisted){stopped=false;schedule();}});
document.addEventListener('visibilitychange',()=>{if(!document.hidden)poll();});
window.addEventListener('online',poll);window.addEventListener('focus',()=>{apply();poll();});
schedule();
})();
