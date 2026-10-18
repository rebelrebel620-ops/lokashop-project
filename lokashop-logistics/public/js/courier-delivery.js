/* Local QR decoding; no camera frames or proof photos leave the device until submission. */
(() => {
 'use strict';
 const video=document.getElementById('qr-video'), canvas=document.getElementById('qr-canvas');
 const message=document.getElementById('scan-message'), start=document.getElementById('start-scan'), stopButton=document.getElementById('stop-scan');
 let stream=null, running=false, timer=null, generation=0;
 const say=text=>{message.textContent=text;};
 function stop(){generation++;running=false;clearTimeout(timer);if(stream)stream.getTracks().forEach(t=>t.stop());stream=null;video.srcObject=null;video.hidden=true;stopButton.hidden=true;start.disabled=false;}
 function decode(source,width,height){
  if(!width||!height)return null;
  const ratio=Math.min(1,1280/Math.max(width,height));canvas.width=Math.round(width*ratio);canvas.height=Math.round(height*ratio);
  const context=canvas.getContext('2d',{willReadFrequently:true});context.drawImage(source,0,0,canvas.width,canvas.height);
  const pixels=context.getImageData(0,0,canvas.width,canvas.height);
  if(typeof window.jsQR!=='function')throw new Error('QR reader could not load. Enter the printed tracking code.');
  const result=window.jsQR(pixels.data,pixels.width,pixels.height,{inversionAttempts:'attemptBoth'});return result?.data||null;
 }
 function found(value){stop();document.getElementById('scan-code').value=value;say('QR read. Opening your parcel…');document.getElementById('parcel-lookup').requestSubmit();}
 async function frame(){if(!running)return;try{const value=decode(video,video.videoWidth,video.videoHeight);if(value){found(value);return;}}catch(error){stop();say(error.message);return;}timer=setTimeout(frame,180);}
 start?.addEventListener('click',async()=>{
  stop();const attempt=generation;start.disabled=true;
  if(!navigator.mediaDevices?.getUserMedia){start.disabled=false;say('Live camera needs HTTPS or localhost. Use Scan QR from photo, or enter the printed tracking code.');return;}
  try{const acquired=await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'},width:{ideal:1280}},audio:false});
   if(attempt!==generation){acquired.getTracks().forEach(t=>t.stop());return;}
   stream=acquired;video.srcObject=stream;video.hidden=false;stopButton.hidden=false;await video.play();if(attempt!==generation)return;running=true;say('Point the camera at the parcel QR label.');frame();
  }catch(error){stop();say('Camera unavailable or permission denied. Use a QR photo or enter the printed tracking code.');}
 });
 stopButton?.addEventListener('click',stop);window.addEventListener('pagehide',stop);document.addEventListener('visibilitychange',()=>{if(document.hidden)stop();});
 document.getElementById('qr-photo')?.addEventListener('change',async event=>{
  stop();const file=event.target.files[0];if(!file)return;const url=URL.createObjectURL(file);
  try{const image=new Image();image.src=url;await image.decode();const value=decode(image,image.naturalWidth,image.naturalHeight);if(value)found(value);else say('No QR found. Choose a clear, close photo of the label or enter its tracking code.');}catch(error){say('Cannot read this QR image. Try JPG or PNG, or enter the tracking code.');}finally{URL.revokeObjectURL(url);event.target.value='';}
 });
 document.querySelectorAll('.delivery-form').forEach(form=>{
  const input=form.querySelector('.proof-input'),preview=form.querySelector('.proof-preview'),note=form.querySelector('.proof-message');let previewURL=null,pending=false,revision=0;
  input.addEventListener('change',async()=>{
   const current=++revision;input.setCustomValidity('');note.textContent='';preview.hidden=true;if(previewURL)URL.revokeObjectURL(previewURL);
   const file=input.files[0];if(!file)return;
   if(!['image/jpeg','image/png'].includes(file.type)){input.value='';note.textContent='Choose a JPG or PNG photo. Set your phone camera to a compatible format if needed.';return;}
   pending=true;note.textContent='Preparing photo…';
   try{
    const image=new Image();const url=URL.createObjectURL(file);
    try{image.src=url;await image.decode();
     const scale=Math.min(1,2000/Math.max(image.naturalWidth,image.naturalHeight));
     const surface=document.createElement('canvas');surface.width=Math.round(image.naturalWidth*scale);surface.height=Math.round(image.naturalHeight*scale);surface.getContext('2d').drawImage(image,0,0,surface.width,surface.height);
     const blob=await new Promise(resolve=>surface.toBlob(resolve,'image/jpeg',0.86));
     if(current!==revision)return;
     if(blob&&typeof DataTransfer!=='undefined'){const transfer=new DataTransfer();transfer.items.add(new File([blob],'delivery-proof.jpg',{type:'image/jpeg'}));input.files=transfer.files;}
    }finally{URL.revokeObjectURL(url);}
    if(input.files[0].size>5*1024*1024){input.setCustomValidity('Photo must be 5 MB or smaller.');note.textContent='Photo is too large. Choose a smaller JPG or PNG.';return;}
    previewURL=URL.createObjectURL(input.files[0]);preview.src=previewURL;preview.hidden=false;note.textContent='Photo ready. Check the image, then enter the buyer OTP and COD collected.';
   }catch(error){input.value='';note.textContent='This photo could not be opened. Take another photo or select JPG/PNG.';}finally{if(current===revision)pending=false;}
  });
  form.addEventListener('submit',event=>{if(pending){event.preventDefault();event.stopImmediatePropagation();form.dataset.submitted='';note.textContent='Please wait for the photo preview before submitting.';}},true);
 });
})();
