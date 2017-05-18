<?php

namespace Jeylabs\AuditLog;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Jeylabs\AuditLog\Models\AuditLog;

class AuditLogController extends Controller
{

    public function pushLocation(Request $request)
    {
        $recordVisiting = config('laravel-audit-log.record_visiting');
        $auditLatitude = $request->input('audit_latitude');
        $auditLongitude = $request->input('audit_longitude');
        if($auditLatitude){
            setcookie('audit_latitude', $auditLatitude, time() + (86400 * 30), "/");
        }
        if ($auditLongitude){
            setcookie('audit_longitude', $auditLongitude, time() + (86400 * 30), "/");
        }
        $oldAuditLatitude = isset($_COOKIE['audit_latitude']) ? $_COOKIE['audit_latitude'] : null;
        $oldAuditLongitude = isset($_COOKIE['audit_longitude']) ? $_COOKIE['audit_longitude'] : null;
        if ($recordVisiting){
            $auditLog = AuditLog::find($request->input('audit_id'));
            if ($auditLog){
                $auditLog->latitude = $auditLatitude ? $auditLatitude : $oldAuditLatitude;
                $auditLog->longitude = $auditLongitude ? $auditLongitude : $oldAuditLongitude;
                $auditLog->save();
            }
        }

    }
}
