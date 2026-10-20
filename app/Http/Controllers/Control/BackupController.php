<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\BackupRun;
use App\Services\CriticalAudit;
use App\Services\DatabaseBackups;
use App\Services\SecurityMutation;
use App\Support\OperationValidation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class BackupController extends Controller
{
    public function index()
    {
        Gate::authorize('backups.view');

        return view('control.backups', ['backups' => BackupRun::latest('id')->paginate(25), 'rehearsals' => DB::table('backup_restore_rehearsals')->latest('id')->paginate(25, ['*'], 'rehearsal_page')]);
    }

    public function request(Request $r)
    {
        $run = app(DatabaseBackups::class)->request($r->user());

        return back()->with('status', 'Respaldo #'.$run->id.' solicitado. El operador de mantenimiento debe ejecutar su generación desde consola.');
    }

    public function verify(Request $r, BackupRun $backup)
    {
        app(DatabaseBackups::class)->verify($r->user(), $backup);

        return back()->with('status', 'Integridad del archivo verificada. El ensayo de restauración se registra por separado.');
    }

    public function download(Request $r, BackupRun $backup)
    {
        $path = app(DatabaseBackups::class)->download($r->user(), $backup);

        return response()->download($path, 'sga-respaldo-'.$backup->id.'.sql', ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function manifest(BackupRun $backup)
    {
        Gate::authorize('backups.inspect');

        return response()->json($backup->manifest ?? [], 200, ['Cache-Control' => 'private, no-store']);
    }

    public function rehearsal(Request $r, BackupRun $backup)
    {
        $data = OperationValidation::make($r->all(), ['reason' => 'required|string|max:1000'])->validate();
        $id = app(DatabaseBackups::class)->requestRehearsal($r->user(), $backup, $data['reason']);

        return back()->with('status', 'Ensayo aislado #'.$id.' solicitado; requiere autorización de otra persona.');
    }

    public function approve(Request $r, int $rehearsal)
    {
        app(DatabaseBackups::class)->approveRehearsal($r->user(), $rehearsal);

        return back()->with('status', 'Ensayo autorizado por 24 horas. Su ejecución se realiza desde consola.');
    }

    public function revoke(Request $r, int $rehearsal)
    {
        $data = OperationValidation::make($r->all(), ['reason' => 'required|string|max:1000'])->validate();
        app(SecurityMutation::class)->run($r->user(), function ($u) use ($rehearsal, $data) {
            app(SecurityMutation::class)->requirePermissions($u, 'backup_rehearsals.authorize');
            $row = DB::table('backup_restore_rehearsals')->where('id', $rehearsal)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($row->status, ['requested', 'approved']), 409, 'El ensayo ya comenzó o está cerrado.');
            DB::table('backup_restore_rehearsals')->where('id', $rehearsal)->update(['status' => 'revoked', 'updated_at' => now()]);
            app(CriticalAudit::class)->record($u, 'rehearsal.revoked', 'rehearsal_id', $rehearsal, ['reason' => $data['reason']]);
        }, protectAdministrativeAccess: false);

        return back()->with('status', 'Autorización de ensayo revocada.');
    }
}
