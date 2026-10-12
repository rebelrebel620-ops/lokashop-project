<?php
namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class Registration
{
    public static function normalizePhone(string $phone): string
    {
        $phone = trim($phone);
        return str_starts_with($phone, '+63') ? '0'.substr($phone, 3) : $phone;
    }

    public static function create(Request $r): int
    {
        $r->merge(['email' => mb_strtolower(trim((string)$r->input('email'))),
            'phone' => self::normalizePhone((string)$r->input('phone')),
            'first_name' => trim((string)$r->input('first_name')),
            'last_name' => trim((string)$r->input('last_name')),
            'business_name' => trim((string)$r->input('business_name'))]);
        $name = ['required', 'string', 'min:2', 'max:50', "regex:/^[\\p{L}\\p{M} .'-]+$/u"];
        $upload = ['file', 'mimes:jpg,jpeg,png,pdf', 'extensions:jpg,jpeg,png,pdf', 'max:5120'];
        $seller = $r->input('role') === 'seller';
        $rules = [
            'role' => ['required', Rule::in(['buyer', 'seller'])],
            'first_name' => $name, 'last_name' => $name,
            'email' => ['required', 'string', 'email:rfc', 'regex:/^[^@\s]+@[^@\s]+\.[^@\s]+$/', 'max:190', function($a,$v,$fail){
                if (DB::table('users')->whereRaw('LOWER(TRIM(email)) = ?', [$v])->exists()) $fail('This email is already in use.');
            }],
            'phone' => ['required', 'regex:/^09[0-9]{9}$/', function($a,$v,$fail){
                if (DB::table('users')->where('phone_normalized',$v)->orWhereRaw('TRIM(phone) IN (?, ?)', [$v, '+63'.substr($v,1)])->exists()) $fail('This mobile number is already in use.');
            }],
            'password' => ['required', 'string', Password::min(8)->mixedCase()->numbers()->symbols()],
            'password_confirmation' => ['required', 'string', Rule::when($r->filled('password'), ['same:password'])],
            'terms' => ['accepted'],
            'region' => 'required|string|max:120', 'province' => 'required|string|max:120',
            'city' => 'required|string|max:120', 'barangay' => 'required|string|max:120',
            'street' => 'required|string|max:200', 'postal_code' => ['required','regex:/^[0-9]{4}$/'],
        ];
        if (!$seller) $rules['date_of_birth'] = ['required','date_format:Y-m-d','before_or_equal:'.now()->subYears(18)->toDateString()];
        if ($seller) {
            $rules += [
                'business_name' => ['required','string','min:3','max:30',function($a,$v,$fail){
                    if(DB::table('seller_businesses')->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($v)])->exists()) $fail('This shop name is already in use.');
                }],
                'shop_category' => ['required',Rule::in(config('registration.categories'))],
                'seller_type' => ['required',Rule::in(['Individual','Sole Proprietorship','Corporation'])],
                'tin' => ['required','regex:/^[0-9]{3}-[0-9]{3}-[0-9]{3}-[0-9]{3}$/',Rule::unique('users','tin')],
                'id_document' => array_merge(['required'],$upload),
                'dti_document' => array_merge([Rule::requiredIf($r->input('seller_type') === 'Sole Proprietorship'),'nullable'],$upload),
                'sec_document' => array_merge([Rule::requiredIf($r->input('seller_type') === 'Corporation'),'nullable'],$upload),
                'bir_document' => array_merge([Rule::requiredIf(in_array($r->input('seller_type'),['Sole Proprietorship','Corporation'],true)),'nullable'],$upload),
            ];
        }
        $v = $r->validate($rules, [
            'first_name.regex'=>'First name may contain only letters, spaces, hyphens, apostrophes and periods.',
            'last_name.regex'=>'Last name may contain only letters, spaces, hyphens, apostrophes and periods.',
            'phone.regex'=>'Enter an 11-digit mobile number starting with 09, or its +639 equivalent.',
            'date_of_birth.before_or_equal'=>'You must be at least 18 years old.',
            'terms.accepted'=>'Please accept the Terms and Privacy Policy.',
            'postal_code.regex'=>'Postal code must contain exactly four digits.',
            'tin.regex'=>'Enter the TIN in the format 000-000-000-000.',
        ]);
        $paths = [];
        try {
            return DB::transaction(function() use($r,$v,$seller,&$paths){
                $id = DB::table('users')->insertGetId([
                    'name'=>$v['first_name'].' '.$v['last_name'], 'first_name'=>$v['first_name'], 'last_name'=>$v['last_name'],
                    'email'=>$v['email'], 'phone'=>$v['phone'], 'phone_normalized'=>$v['phone'],
                    'date_of_birth'=>$v['date_of_birth']??null, 'password'=>Hash::make($v['password']),
                    'role'=>$v['role'], 'approval_status'=>'unverified', 'active'=>1,
                    'verification_required'=>true, 'terms_accepted_at'=>now(),
                    'tin'=>$v['tin']??null, 'seller_type'=>$v['seller_type']??null, 'region'=>$v['region'],
                    'created_at'=>now(), 'updated_at'=>now(),
                ]);
                DB::table('addresses')->insert(['user_id'=>$id,'recipient'=>$v['first_name'].' '.$v['last_name'],
                    'phone'=>$v['phone'],'street'=>$v['street'],'province'=>$v['province'],'city'=>$v['city'],
                    'barangay'=>$v['barangay'],'postal_code'=>$v['postal_code'],'is_default'=>true,'created_at'=>now(),'updated_at'=>now()]);
                if($seller) {
                    DB::table('seller_businesses')->insert(['user_id'=>$id,'name'=>$v['business_name'],
                        'name_normalized'=>mb_strtolower($v['business_name']),'category'=>$v['shop_category'],'created_at'=>now(),'updated_at'=>now()]);
                    foreach(['id_document'=>'id','dti_document'=>'dti','sec_document'=>'sec','bir_document'=>'bir'] as $field=>$kind){
                        if(!$r->hasFile($field)) continue;
                        $path=$r->file($field)->store('documents'); $paths[]=$path;
                        DB::table('registration_documents')->insert(['user_id'=>$id,'kind'=>$kind,'path'=>$path,'created_at'=>now(),'updated_at'=>now()]);
                    }
                }
                self::issueOtp($id, $v['email']);
                return $id;
            });
        } catch(\Throwable $e) {
            foreach($paths as $path) \Illuminate\Support\Facades\Storage::delete($path);
            if($e instanceof \Illuminate\Database\UniqueConstraintViolationException)
                throw ValidationException::withMessages(['email'=>'An account with these email, mobile, shop or TIN details already exists.']);
            throw $e;
        }
    }

    public static function issueOtp(int $id, string $email): void
    {
        $previous = DB::table('registration_otps')->where('user_id',$id)->first();
        do { $code = (string)random_int(100000,999999); }
        while ($previous && Hash::check($code,$previous->code_hash));
        DB::table('registration_otps')->updateOrInsert(['user_id'=>$id], [
            'code_hash'=>Hash::make($code), 'expires_at'=>now()->addMinutes(5), 'attempts'=>0, 'sent_at'=>now()]);
        Mail::raw("Your LokaShop verification code is {$code}. It expires in 5 minutes. Do not share it.",
            function($message)use($email){$message->to($email)->subject('Verify your LokaShop email');});
    }
}
