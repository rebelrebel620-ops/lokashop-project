<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{$title}}</title>
<style>body{font:16px/1.5 system-ui;background:#f3f7f4;color:#183b2c;margin:auto;padding:30px;max-width:720px}section{background:white;border:1px solid #dde5df;border-radius:12px;padding:24px;margin:20px 0}input,select,textarea{box-sizing:border-box;display:block;width:100%;padding:10px;border:1px solid #bbc8bf;border-radius:6px}input[type=checkbox]{width:auto}button{padding:12px;background:#18613e;color:white;border:0;border-radius:6px}.error{color:#b91c1c}</style>
<a href="/login">Log in</a> · <a href="/application/resubmit">Correct rejected application</a><h1>{{$title}}</h1>
@if(session('success'))<p>{{session('success')}}</p>@endif
@if($errors->any())<div class="error">@foreach($errors->all() as $error)<p>{{$error}}</p>@endforeach</div>@endif
@foreach($forms as $form)<section><h2>{{$form['label']}}</h2>@include('tool-form',['form'=>$form])</section>@endforeach
@include('shared-interactions')
</html>
