@if(config('laravel-audit-log.track_location'))
    <script>
        var auditLogVariables = {};
        auditLogVariables.prefix = '{{ route('audit.log.push.location') }}';
        auditLogVariables.activity_log_id = null;
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(updateLocation, showError, {
                enableHighAccuracy: true
            });
        } else {
            console.warn('Geolocation is not supported by this browser.')
        }
        function updateLocation(position) {
            @if(config('laravel-audit-log.record_visiting'))
                auditLogVariables.activity_log_id = '{{  activity('Visited')->causedBy(auth()->user())->log(url()->current())->id }}';
                    @endif
            var xHttp = new XMLHttpRequest();
            xHttp.onreadystatechange = function () {
                if (this.readyState === 4 && this.status === 200) {
                }
            };
            auditLogVariables.url = auditLogVariables.prefix + '?audit_latitude=' + position.coords.latitude + '&audit_longitude=' + position.coords.longitude + '&audit_id=' + auditLogVariables.activity_log_id;
            xHttp.open("POST", auditLogVariables.url, true);
            xHttp.send();
        }

        function showError(error) {
            switch (error.code) {
                case error.PERMISSION_DENIED:
                    console.warn("User denied the request for Geolocation.");
                    break;
                case error.POSITION_UNAVAILABLE:
                    console.warn("Location information is unavailable.");
                    break;
                case error.TIMEOUT:
                    console.warn("The request to get user location timed out.");
                    break;
                case error.UNKNOWN_ERROR:
                    console.warn("An unknown error occurred.");
                    break;
            }
        }
    </script>
@endif
