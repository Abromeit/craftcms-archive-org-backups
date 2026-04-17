document.addEventListener('DOMContentLoaded', function() {
    var root = document.querySelector('[data-aob-dashboard]');

    if (!root) {
        return;
    }

    var endpoint = root.getAttribute('data-refresh-endpoint');
    var viewerToken = root.getAttribute('data-viewer-token');

    if (!endpoint || !viewerToken) {
        return;
    }

    var refresh = function() {
        var visibleIds = [];
        var rows = root.querySelectorAll('[data-target-id]');
        var index;
        var currentUrl = new URL(window.location.href);

        for (index = 0; index < rows.length; ++index) {
            visibleIds.push(rows[index].getAttribute('data-target-id'));
        }

        var url = new URL(endpoint, window.location.origin);

        url.searchParams.set('viewerToken', viewerToken);

        if (currentUrl.searchParams.has('sort')) {
            url.searchParams.set('sort', currentUrl.searchParams.get('sort'));
        }

        if (currentUrl.searchParams.has('dir')) {
            url.searchParams.set('dir', currentUrl.searchParams.get('dir'));
        }

        if (visibleIds.length > 0) {
            url.searchParams.set('visibleTargetIds', visibleIds.join(','));
        }

        fetch(url.toString(), {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json'
            }
        })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Refresh failed.');
                }

                return response.json();
            })
            .then(function(payload) {
                if (payload.dashboardHtml) {
                    root.innerHTML = payload.dashboardHtml;
                }
            })
            .catch(function() {
            });
    };

    window.setInterval(refresh, 60000);
});
