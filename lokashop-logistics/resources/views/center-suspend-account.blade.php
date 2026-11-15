<div class="courier-account-actions">
@if($member->approval_status==='approved'&&$member->active&&$member->account_status==='active')
<button type="button" class="admin-manage-status" data-courier-suspend="courier-suspend-{{$member->id}}" aria-haspopup="dialog" aria-controls="courier-suspend-{{$member->id}}">Manage account</button>
<dialog id="courier-suspend-{{$member->id}}" class="admin-status-dialog courier-suspend-dialog" aria-labelledby="courier-suspend-title-{{$member->id}}" @if((string)old('id')===(string)$member->id&&(string)old('active')==='0'&&$errors->any()) data-reopen-courier-suspend @endif>
<header><div><span class="admin-eyebrow">ACCOUNT STATUS</span><h2 id="courier-suspend-title-{{$member->id}}">Suspend courier account</h2></div><button type="button" class="admin-dialog-close" data-close-courier-suspend aria-label="Close suspension dialog">×</button></header>
<p class="admin-dialog-person"><strong>{{$member->name}}</strong><br>{{$member->email}}<br><small>Current status: {{ucfirst($member->account_status)}}</small></p>
<form method="post" action="/ops/rider/status">@csrf<input type="hidden" name="id" value="{{$member->id}}"><input type="hidden" name="active" value="0">
<label>New account status</label><p class="courier-suspend-state">Suspended</p>
<label for="courier-suspend-reason-{{$member->id}}">Reason for suspension</label><textarea id="courier-suspend-reason-{{$member->id}}" name="reason" minlength="10" maxlength="300" rows="3" required placeholder="Explain why you are suspending this courier (10–300 characters)">{{(string)old('id')===(string)$member->id?old('reason'):''}}</textarea>
@if((string)old('id')===(string)$member->id)@foreach(['reason','active','id'] as $field)@error($field)<p class="courier-suspend-error" role="alert">{{$message}}</p>@enderror @endforeach @endif
<p class="courier-suspend-help">Accepted jobs must be finished first. Suspension turns the courier offline and prevents new work.</p>
<footer><button type="button" class="admin-dialog-cancel" data-close-courier-suspend>Cancel</button><button type="submit" class="admin-dialog-save">Suspend account</button></footer>
</form></dialog>
@else
<p class="design-hint">{{$member->account_status==='suspended'?'Account suspended. Review details or the existing appeal process to restore access.':($member->approval_status!=='approved'?'Complete activation and application approval before managing access.':'This account requires logistics administrator review.')}}</p>
@endif
@if($member->approval_status==='pending_activation')<form method="post" action="/ops/rider/resend">@csrf<input type="hidden" name="id" value="{{$member->id}}"><button>Resend activation email</button></form>@endif
</div>
