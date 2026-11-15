(() => {
 let opener;
 function open(dialog,button){if(!dialog||dialog.open)return;opener=button;dialog.showModal();dialog.querySelector('select,input:not([type="hidden"]),textarea,button')?.focus();dialog.addEventListener('close',()=>{opener?.focus();opener=null;},{once:true});}
 document.addEventListener('click',event=>{const caseButton=event.target.closest('[data-return-case]');if(caseButton){const action=caseButton.hasAttribute('data-return-receive')?'/tools/return/received':'/tools/return/respond';const dialog=document.querySelector(`dialog[data-return-action="${action}"]`);if(!dialog)return;const record=dialog.querySelector('[name="id"]');if(record)record.value=caseButton.dataset.returnCase;const decision=dialog.querySelector('[name="decision"]');if(decision)decision.value=caseButton.dataset.returnDecision||'approved';open(dialog,caseButton);return;}const button=event.target.closest('[data-return-dialog]');if(button){open(document.getElementById(button.dataset.returnDialog),button);return;}const close=event.target.closest('[data-close-return-dialog]');if(close)close.closest('dialog')?.close();});
 document.querySelectorAll('dialog[data-return-action]').forEach(dialog=>{const form=dialog.querySelector('form');if(form){const marker=document.createElement('input');marker.type='hidden';marker.name='_return_action';marker.value=dialog.dataset.returnAction;form.append(marker);}});
 const failed=document.querySelector('[data-reopen-return-dialog]');if(failed)open(failed,document.querySelector(`[data-return-dialog="${failed.id}"]`));
 document.querySelectorAll('.return-evidence-form').forEach(form=>{
  form.querySelector('[name="photos[]"]')?.addEventListener('change',event=>{const input=event.target;input.setCustomValidity(input.files.length>5||Array.from(input.files).some(file=>file.size>5*1048576)?'Select up to five photos, no larger than 5 MB each.':'');});
  form.querySelector('[name="video"]')?.addEventListener('change',event=>{const input=event.target;input.setCustomValidity(input.files[0]?.size>50*1048576?'Video proof must be 50 MB or smaller.':'');});
 });
})();
