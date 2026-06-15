import './bootstrap';

import Alpine from 'alpinejs';

const STORAGE_KEY = 'coreti-theme';
const root = document.documentElement;
const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
const themeColorMeta = document.querySelector('meta[name="theme-color"]');

function getStoredTheme() {
    return 'light';
}

function resolveTheme(theme) {
    return 'light';
}

function applyTheme(theme) {
    root.classList.remove('dark');
    root.dataset.theme = 'light';

    if (themeColorMeta) {
        themeColorMeta.setAttribute('content', '#f8fafc');
    }
}

window.setTheme = (theme) => {
    localStorage.setItem(STORAGE_KEY, 'light');
    applyTheme('light');
};

window.getTheme = getStoredTheme;

applyTheme('light');

window.Alpine = Alpine;

Alpine.start();
