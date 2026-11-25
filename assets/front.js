document.addEventListener('DOMContentLoaded', function () {
    var referrer = document.referrer || '';

    function send(action, extra) {
        var params = new URLSearchParams();
        params.append('action', action);
        params.append('referrer', referrer);
        Object.keys(extra || {}).forEach(function (key) {
            params.append(key, extra[key]);
        });

        if (navigator.sendBeacon) {
            var blob = new Blob(
                [params.toString()],
                { type: 'application/x-www-form-urlencoded' }
            );
            navigator.sendBeacon(biolinksData.ajax_url, blob);
        } else {
            fetch(biolinksData.ajax_url, {
                method: 'POST',
                body: params,
                keepalive: true,
            });
        }
    }

    function applySchedule() {
        var now = Math.floor(Date.now() / 1000);
        var next = 0;

        document.querySelectorAll('[data-start], [data-end]').forEach(function (el) {
            var start = parseInt(el.getAttribute('data-start') || '0', 10);
            var end = parseInt(el.getAttribute('data-end') || '0', 10);
            el.hidden = (start && now < start) || (end && now >= end);

            [start, end].forEach(function (ts) {
                if (ts > now && (!next || ts < next)) {
                    next = ts;
                }
            });
        });

        if (next && next - now < 86400) {
            setTimeout(applySchedule, (next - now) * 1000 + 500);
        }
    }

    applySchedule();

    send('biolinks_view');

    document.querySelectorAll('.biolinks-link, .biolinks-social').forEach(function (el) {
        el.addEventListener('click', function () {
            var id = this.getAttribute('data-id');
            if (!id) return;
            send('biolinks_click', { link_id: id });
        });
    });
});
