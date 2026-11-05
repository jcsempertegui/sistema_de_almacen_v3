<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\EjecucionRespaldo;
use App\Services\AuditoriaCritica;
use App\Services\ModificacionSegura;
use App\Services\RespaldosBaseDatos;
use App\Support\ValidacionOperacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ControladorRespaldo extends Controller
{
    public function index()
    {
        Gate::authorize('backups.view');

        return view('control.backups', ['backups' => EjecucionRespaldo::latest('id')->paginate(25), 'rehearsals' => DB::table('ensayos_restauracion_respaldo')->latest('id')->paginate(25, ['*'], 'rehearsal_page')]);
    }

    public function request(Request $r)
    {
        $run = app(RespaldosBaseDatos::class)->request($r->user());

        return back()->with('estado', 'Respaldo #'.$run->id.' solicitado. El operador de mantenimiento debe ejecutar su generación desde consola.');
    }

    public function verify(Request $r, EjecucionRespaldo $backup)
    {
        app(RespaldosBaseDatos::class)->verify($r->user(), $backup);

        return back()->with('estado', 'Integridad del archivo verificada. El ensayo de restauración se registra por separado.');
    }

    public function download(Request $r, EjecucionRespaldo $backup)
    {
        $path = app(RespaldosBaseDatos::class)->download($r->user(), $backup);

        return response()->download($path, 'sga-respaldo-'.$backup->id.'.sql', ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function manifiesto(EjecucionRespaldo $backup)
    {
        Gate::authorize('backups.inspect');

        return response()->json($backup->manifiesto ?? [], 200, ['Cache-Control' => 'private, no-store']);
    }

    public function rehearsal(Request $r, EjecucionRespaldo $backup)
    {
        $data = ValidacionOperacion::make($r->all(), ['motivo' => 'required|string|max:1000'])->validate();
        $id = app(RespaldosBaseDatos::class)->requestRehearsal($r->user(), $backup, $data['motivo']);

        return back()->with('estado', 'Ensayo aislado #'.$id.' solicitado; requiere autorización de otra persona.');
    }

    public function approve(Request $r, int $rehearsal)
    {
        app(RespaldosBaseDatos::class)->approveRehearsal($r->user(), $rehearsal);

        return back()->with('estado', 'Ensayo autorizado por 24 horas. Su ejecución se realiza desde consola.');
    }

    public function revoke(Request $r, int $rehearsal)
    {
        $data = ValidacionOperacion::make($r->all(), ['motivo' => 'required|string|max:1000'])->validate();
        app(ModificacionSegura::class)->run($r->user(), function ($u) use ($rehearsal, $data) {
            app(ModificacionSegura::class)->exigirPermisos($u, 'backup_rehearsals.authorize');
            $row = DB::table('ensayos_restauracion_respaldo')->where('id', $rehearsal)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($row->estado, ['requested', 'approved']), 409, 'El ensayo ya comenzó o está cerrado.');
            DB::table('ensayos_restauracion_respaldo')->where('id', $rehearsal)->update(['estado' => 'revoked', 'fecha_actualizacion' => now()]);
            app(AuditoriaCritica::class)->record($u, 'rehearsal.revoked', 'ensayo_id', $rehearsal, ['motivo' => $data['motivo']]);
        }, protectAdministrativeAccess: false);

        return back()->with('estado', 'Autorización de ensayo revocada.');
    }
}
