(()=>{
 const button=document.querySelector('.phone-nav-toggle'),nav=document.getElementById('logistics-header-nav');
 if(!button||!nav)return;
 const phone=window.matchMedia('(max-width:600px)');
 const close=()=>{document.body.classList.remove('phone-menu-open');button.setAttribute('aria-expanded','false');button.setAttribute('aria-label','Open navigation menu');};
 document.body.classList.add('phone-nav-ready');
 button.addEventListener('click',()=>{if(!phone.matches)return;const open=document.body.classList.toggle('phone-menu-open');button.setAttribute('aria-expanded',String(open));button.setAttribute('aria-label',open?'Close navigation menu':'Open navigation menu');});
 document.addEventListener('keydown',event=>{if(phone.matches&&event.key==='Escape'&&document.body.classList.contains('phone-menu-open')){close();button.focus();}});
 document.addEventListener('click',event=>{if(phone.matches&&!event.target.closest('header.m-header'))close();});
 const resize=()=>{if(!phone.matches)close();};
 if(phone.addEventListener)phone.addEventListener('change',resize);else phone.addListener(resize);
})();
