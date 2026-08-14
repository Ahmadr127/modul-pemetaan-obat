/**
 * Toast notification helper (window.Toast).
 * Di-refer oleh layouts/app.blade.php untuk menampilkan flash message.
 */
(function (window) {
    'use strict';

    var COLORS = {
        success: '#16a34a',
        error: '#dc2626',
        warning: '#d97706',
        info: '#007774',
    };

    function container() {
        var el = document.getElementById('toast-container');
        if (!el) {
            el = document.createElement('div');
            el.id = 'toast-container';
            el.style.position = 'fixed';
            el.style.top = '1rem';
            el.style.right = '1rem';
            el.style.zIndex = '9999';
            el.style.display = 'flex';
            el.style.flexDirection = 'column';
            el.style.gap = '0.5rem';
            el.style.maxWidth = '24rem';
            document.body.appendChild(el);
        }
        return el;
    }

    function show(message, type) {
        var el = document.createElement('div');
        el.style.backgroundColor = COLORS[type] || COLORS.info;
        el.style.color = '#ffffff';
        el.style.padding = '0.75rem 1rem';
        el.style.borderRadius = '0.5rem';
        el.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
        el.style.fontSize = '0.875rem';
        el.style.fontWeight = '600';
        el.style.lineHeight = '1.4';
        el.style.whiteSpace = 'pre-line';
        el.textContent = message;

        var close = document.createElement('button');
        close.textContent = '\u00d7';
        close.style.cssFloat = 'right';
        close.style.background = 'transparent';
        close.style.border = 'none';
        close.style.color = 'inherit';
        close.style.fontSize = '1rem';
        close.style.cursor = 'pointer';
        close.style.marginLeft = '0.5rem';
        close.style.opacity = '0.8';
        close.setAttribute('aria-label', 'Tutup');
        close.onclick = function () {
            el.remove();
        };
        el.appendChild(close);

        container().appendChild(el);

        window.setTimeout(function () {
            el.remove();
        }, 8000);
    }

    window.Toast = {
        success: function (message) { show(message, 'success'); },
        error: function (message) { show(message, 'error'); },
        warning: function (message) { show(message, 'warning'); },
        info: function (message) { show(message, 'info'); },
    };
})(window);
