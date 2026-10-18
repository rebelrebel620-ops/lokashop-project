(() => {
 'use strict';
 const main=document.getElementById('shop-main-category');
 main?.addEventListener('change',()=>{const child=document.getElementById('shop-subcategory');if(child)child.value='';main.form.requestSubmit();});
 document.getElementById('shop-subcategory')?.addEventListener('change',event=>event.target.form.requestSubmit());
 const search=document.getElementById('contact-search');
 search?.addEventListener('input',()=>{const query=search.value.trim().toLocaleLowerCase();let visible=0;document.querySelectorAll('[data-contact-name]').forEach(contact=>{const match=contact.dataset.contactName.includes(query);contact.hidden=!match;if(match)visible++;});document.getElementById('contact-search-empty').hidden=visible>0;});
 const thread=document.getElementById('conversation-thread');if(thread)thread.scrollTop=thread.scrollHeight;
 document.getElementById('message-attachment')?.addEventListener('change',event=>{const input=event.target,file=input.files[0];input.setCustomValidity(file&&file.size>5*1024*1024?'Choose a photo of 5 MB or smaller.':'');document.getElementById('attachment-name').textContent=file?file.name:'JPG or PNG · up to 5 MB';});
 const photo=document.getElementById('profile-photo');let previewURL=null;
 photo?.addEventListener('change',()=>{
  const file=photo.files[0],feedback=document.getElementById('profile-photo-feedback');photo.setCustomValidity('');feedback.textContent='';if(!file)return;
  if(!['image/jpeg','image/png'].includes(file.type)||file.size>5*1024*1024){photo.setCustomValidity('Choose a JPG or PNG photo up to 5 MB.');feedback.textContent='Please choose a JPG or PNG photo up to 5 MB.';return;}
  const avatar=document.getElementById('profile-avatar');if(previewURL)URL.revokeObjectURL(previewURL);previewURL=URL.createObjectURL(file);const img=new Image();img.alt='Preview of your new profile picture';img.src=previewURL;avatar.replaceChildren(img);feedback.textContent='Preview ready. Save profile to keep this photo.';
 });
 window.addEventListener('pagehide',()=>{if(previewURL)URL.revokeObjectURL(previewURL);});
})();
