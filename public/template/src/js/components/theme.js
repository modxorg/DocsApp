/**
 * Theme toggle — cycles system → light → dark → system
 */

const STORAGE_KEY = 'modx-docs-theme';
const PREFERENCES = ['system', 'light', 'dark'];

const LABELS = {
    system: 'Theme: system (click for light)',
    light: 'Theme: light (click for dark)',
    dark: 'Theme: dark (click for system)',
};

class Theme {
    getClassName() { return 'Theme'; }

    constructor() {
        this.media = window.matchMedia('(prefers-color-scheme: dark)');
        this.button = document.querySelector('[data-theme-toggle]');
        this.metaTheme = document.querySelector('meta[name="theme-color"]');

        this.apply(this.getPreference());
        this.bindEvents();
    }

    getPreference() {
        const stored = window.localStorage.getItem(STORAGE_KEY);
        if (PREFERENCES.indexOf(stored) !== -1) {
            return stored;
        }
        return 'system';
    }

    resolve(preference) {
        if (preference === 'system') {
            return this.media.matches ? 'dark' : 'light';
        }
        return preference;
    }

    apply(preference) {
        const resolved = this.resolve(preference);
        const root = document.documentElement;

        root.setAttribute('data-theme-preference', preference);
        root.setAttribute('data-theme', resolved);

        try {
            window.localStorage.setItem(STORAGE_KEY, preference);
        } catch (e) {
            // ignore quota / private mode errors
        }

        if (this.metaTheme) {
            const styles = getComputedStyle(root);
            const themeColor = styles.getPropertyValue('--color-theme-color').trim();
            if (themeColor) {
                this.metaTheme.setAttribute('content', themeColor);
            }
        }

        if (this.button) {
            this.button.setAttribute('aria-label', LABELS[preference]);
            this.button.setAttribute('title', LABELS[preference]);
        }
    }

    cycle() {
        const current = this.getPreference();
        const next = PREFERENCES[(PREFERENCES.indexOf(current) + 1) % PREFERENCES.length];
        this.apply(next);
    }

    bindEvents() {
        if (this.button) {
            this.button.addEventListener('click', (e) => {
                e.preventDefault();
                this.cycle();
            });
        }

        const onChange = () => {
            if (this.getPreference() === 'system') {
                this.apply('system');
            }
        };

        if (typeof this.media.addEventListener === 'function') {
            this.media.addEventListener('change', onChange);
        } else if (typeof this.media.addListener === 'function') {
            this.media.addListener(onChange);
        }
    }
}

export default Theme;
