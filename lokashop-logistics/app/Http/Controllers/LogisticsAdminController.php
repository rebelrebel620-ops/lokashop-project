<?php
namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LogisticsAdminController extends Controller
{
    private function accounts()
    {
        return DB::table('users')->whereIn('role', ['sorting_center', 'rider']);
    }

    public function approvals()
    {
        $users = $this->accounts()->where('approval_status', 'pending')->orderBy('id')->paginate(20);
        return view('admin_approvals', [
            'links' => app(CommonController::class)->links(), 'users' => $users,
            'docs' => DB::table('registration_documents')->whereIn('user_id', $users->pluck('id'))->get()->groupBy('user_id'),
        ]);
    }

    public function approve(Request $request, int $id)
    {
        $values = $request->validate(['decision' => 'required|in:approved,rejected']);
        DB::transaction(function () use ($id, $values) {
            abort_unless($this->accounts()->where('id', $id)->where('approval_status', 'pending')
                ->update(['approval_status' => $values['decision'], 'updated_at' => now()]), 422);
            DB::table('notifications')->insert([
                'user_id' => $id, 'body' => 'Your registration is '.$values['decision'],
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
        return back()->with('success', 'Decision saved.');
    }

    public function document(int $id)
    {
        $document = DB::table('registration_documents as d')->join('users as u', 'u.id', '=', 'd.user_id')
            ->where('d.id', $id)->whereIn('u.role', ['sorting_center', 'rider'])->select('d.*')->first();
        abort_unless($document, 404);
        return response()->file(storage_path('app/private/'.$document->path));
    }

    public function users()
    {
        return view('admin_users', [
            'links' => app(CommonController::class)->links(),
            'users' => $this->accounts()->select('id', 'name', 'email', 'role', 'approval_status', 'active')->orderByDesc('id')->paginate(30),
        ]);
    }

    public function toggle(int $id)
    {
        $user = $this->accounts()->where('id', $id)->first();
        abort_unless($user, 404);
        $this->accounts()->where('id', $id)->update(['active' => !$user->active, 'updated_at' => now()]);
        return back()->with('success', 'Account changed.');
    }
}
