document.addEventListener('DOMContentLoaded', function () {
    var trackables = document.querySelectorAll('.biolinks-link, .biolinks-social');

    trackables.forEach(function (el) {
        el.addEventListener('click', function () {
            var id = this.getAttribute('data-id');
            if (!id) return;

            var params = new URLSearchParams();
            params.append('action', 'biolinks_click');
            params.append('link_id', id);
            params.append('nonce', biolinksData.nonce);

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
                });
            }
        });
    });
});
