(function () {
    var storageKey = 'saloneTheme';
    var body = document.body;
    if (!body) return;

    var current = body.getAttribute('data-theme') === 'scuro' ? 'scuro' : 'chiaro';
    try {
        var saved = localStorage.getItem(storageKey);
        if (saved === 'chiaro' || saved === 'scuro') {
            current = saved;
            body.setAttribute('data-theme', current);
        }
    } catch (e) {}

    function syncLabel() {
        var next = body.getAttribute('data-theme') === 'scuro' ? 'chiaro' : 'scuro';
        document.querySelectorAll('.theme-toggle-text').forEach(function (node) {
            node.textContent = 'tema ' + next;
        });
    }

    syncLabel();

    document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            var next = body.getAttribute('data-theme') === 'scuro' ? 'chiaro' : 'scuro';
            body.setAttribute('data-theme', next);
            try {
                localStorage.setItem(storageKey, next);
            } catch (e) {}
            syncLabel();
        });
    });
})();
