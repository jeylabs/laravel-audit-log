<script>
    var auditLogVariables = {};
    auditLogVariables.prefix = '{{ route('audit.log.push.location') }}';
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(updateLocation);
    }
    function updateLocation(position) {
        auditLogVariables.activity_log_id = '{{  activity('Visited')->causedBy(auth()->user())->log(url()->current())->id }}';
        var xHttp = new XMLHttpRequest();
        xHttp.onreadystatechange = function () {
            if (this.readyState === 4 && this.status === 200) {
            }
        };
        auditLogVariables.url = auditLogVariables.prefix + '?audit_latitude='+position.coords.latitude+'&audit_longitude='+position.coords.longitude+'&audit_id='+auditLogVariables.activity_log_id;
        xHttp.open("POST", auditLogVariables.url, true);
        xHttp.send();
    }
</script>