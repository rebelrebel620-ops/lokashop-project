@php $returnPopup=isset($returnOrder)&&$returnOrder; $returnOld=!$returnPopup||(string)old('_return_popup')===(string)$returnOrder->id; @endphp
<form method="post" enctype="multipart/form-data" action="/tools/return" class="return-evidence-form">@csrf
<input type="hidden" name="_request_key" value="{{$returnOld?old('_request_key',(string)\Illuminate\Support\Str::uuid()):(string)\Illuminate\Support\Str::uuid()}}">
@if($returnPopup)
<input type="hidden" name="_return_popup" value="{{$returnOrder->id}}"><input type="hidden" name="order_id" value="{{$returnOrder->id}}">
<p class="return-order-summary"><strong>Order #{{$returnOrder->id}}</strong><br>{{$returnOrder->store_name??'LokaShop seller'}}<br><small>{{$returnOrder->tracking_code}}</small></p>
@else
<label>Order<select name="order_id" required><option value="">Choose order</option>@foreach(\App\Services\FormChoices::for('order_id','/tools/return')??[] as $value=>$label)<option value="{{$value}}" @selected((string)old('order_id')===(string)$value)>{{$label}}</option>@endforeach</select></label>
@endif
@if($errors->any()&&$returnOld)<div class="return-form-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{$error}}</li>@endforeach</ul><p>Choose your evidence files again before resubmitting.</p></div>@endif
<label>Reason<select name="reason" required>@foreach(['Damaged','Wrong item','Not as described'] as $reason)<option value="{{$reason}}" @selected($returnOld&&old('reason')===$reason)>{{$reason}}</option>@endforeach</select></label>
<label>Description<textarea name="description" required minlength="10" maxlength="500" rows="3" placeholder="Explain the issue (10–500 characters)">{{$returnOld?old('description'):''}}</textarea></label>
<label>Photos<input type="file" name="photos[]" accept="image/jpeg,image/png" multiple required><small>Upload 1–5 JPG/PNG photos, up to 5 MB each.</small></label>
<label>Video proof<input type="file" name="video" accept="video/mp4,video/webm" required><small>Show the product and issue. MP4/WebM, up to 50 MB. Return evidence is kept in its original quality.</small></label>
<p class="return-form-help">Your seller will review this request. Returns are allowed within seven days after delivery and before order completion.</p>
<footer>@if($returnPopup)<button type="button" class="btn alt" data-close-return-dialog>Cancel</button>@endif<button type="submit" class="btn">Request return</button></footer>
</form>
