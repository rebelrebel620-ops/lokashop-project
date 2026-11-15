(()=>{
 const row=document.getElementById('category-carousel'),controls=document.querySelector('[data-category-controls]');
 if(!row||!controls)return;
 const previous=controls.querySelector('[data-category-prev]'),next=controls.querySelector('[data-category-next]');
 const update=()=>{const max=row.scrollWidth-row.clientWidth;controls.hidden=max<=2;previous.disabled=row.scrollLeft<=2;next.disabled=row.scrollLeft>=max-2;};
 const move=direction=>{const card=row.querySelector('.category-photo-card');const step=card?card.getBoundingClientRect().width+14:240;const count=Math.max(1,Math.floor(row.clientWidth/step));row.scrollBy({left:direction*step*count,behavior:window.matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth'});};
 previous.addEventListener('click',()=>move(-1));next.addEventListener('click',()=>move(1));
 row.addEventListener('keydown',event=>{if(event.target!==row)return;if(['ArrowLeft','ArrowRight'].includes(event.key)){event.preventDefault();move(event.key==='ArrowLeft'?-1:1);}});
 row.addEventListener('scroll',update,{passive:true});row.addEventListener('load',update,true);window.addEventListener('resize',update);
 row.addEventListener('focusin',event=>{if(event.target.matches('.category-photo-card'))event.target.scrollIntoView({block:'nearest',inline:'nearest'});});
 if('ResizeObserver' in window)new ResizeObserver(update).observe(row);update();
})();
