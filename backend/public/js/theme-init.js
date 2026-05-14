(function () {
    'use strict';

    var storedTheme = 'dark';

    try {
        storedTheme = localStorage.getItem('theme') || 'dark';
    } catch (error) {
        storedTheme = 'dark';
    }

    document.documentElement.setAttribute('data-theme', storedTheme);
})();
