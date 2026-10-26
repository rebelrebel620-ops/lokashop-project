@if(config('app.site_kind')==='ecommerce' && $u->account_status==='banned')
<span class="admin-status-locked">Permanently banned</span>
@else
<button type="button" class="admin-manage-status" data-account-dialog="account-status-{{$u->id}}" aria-haspopup="dialog" aria-controls="account-status-{{$u->id}}">Manage status</button>
<dialog id="account-status-{{$u->id}}" class="admin-status-dialog" aria-labelledby="account-status-title-{{$u->id}}">
 <header><div><span class="admin-eyebrow">ACCOUNT STATUS</span><h2 id="account-status-title-{{$u->id}}">Manage account status</h2></div><button type="button" class="admin-dialog-close" data-close-account-dialog aria-label="Close account status">×</button></header>
 <p class="admin-dialog-person"><strong>{{$u->name}}</strong><br>{{$u->email}}<br><small>Current status: {{ucfirst($u->account_status)}}</small></p>
 <form method="post" action="/admin/users/{{$u->id}}/status">@csrf
  <label for="new-status-{{$u->id}}">New account status</label>
  <select id="new-status-{{$u->id}}" name="status" required>@foreach(['active'=>'Active / restored','suspended'=>'Suspended','banned'=>'Banned','archived'=>'Archived'] as $value=>$label)<option value="{{$value}}" @selected(($u->account_status??'active')===$value)>{{$label}}</option>@endforeach</select>
  <label for="status-reason-{{$u->id}}">Reason for this change</label>
  <textarea id="status-reason-{{$u->id}}" name="reason" required minlength="5" maxlength="300" rows="3" placeholder="Explain why you are changing this account's status"></textarea>
  <footer><button type="button" class="admin-dialog-cancel" data-close-account-dialog>Cancel</button><button type="submit" class="admin-dialog-save">Apply status change</button></footer>
 </form>
</dialog>
@endif
