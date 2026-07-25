<?php

namespace Jeylabs\AuditLog;


use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Jeylabs\AuditLog\Contracts\AuditLogStore;

class AuditLogController extends Controller
{

    public function pushLocation(Request $request, AuditLogStore $store)
    {
        $recordVisiting = config('laravel-audit-log.record_visiting');
        $trackLocation = config('laravel-audit-log.track_location', true);

        $auditLatitude = $request->input('audit_latitude');
        $auditLongitude = $request->input('audit_longitude');
        if ($auditLatitude) {
            setcookie('audit_latitude', $auditLatitude, time() + (86400 * 30), "/");
        }
        if ($auditLongitude) {
            setcookie('audit_longitude', $auditLongitude, time() + (86400 * 30), "/");
        }
        $oldAuditLatitude = isset($_COOKIE['audit_latitude']) ? $_COOKIE['audit_latitude'] : null;
        $oldAuditLongitude = isset($_COOKIE['audit_longitude']) ? $_COOKIE['audit_longitude'] : null;

        if ($recordVisiting && $trackLocation) {
            $store->updateLocation(
                $request->input('audit_id'),
                $auditLatitude ? $auditLatitude : $oldAuditLatitude,
                $auditLongitude ? $auditLongitude : $oldAuditLongitude
            );
        }
    }
}
