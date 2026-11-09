/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';

import { isA11yModeEnhanced } from '../a11y_mode.js';
import { initMenus } from '../boilerplates-ui/vanilla/menu.js';
import { applyColorScheme, colorSchemeLabel, currentColorScheme } from '../color_scheme.js';

const ICONS = { auto: 'monitor', light: 'sun', dark: 'moon' };

// Délégation sur le document : à brancher une seule fois, Turbo ne remplace que le <body>.
let menusReady = false;

export default class extends Controller {
    static targets = ['trigger', 'item'];
    static values = { labels: Object };

    connect() {
        if (!menusReady) {
            initMenus();
            menusReady = true;
        }
        this.observer = new MutationObserver(() => this.render());
        this.observer.observe(document.documentElement, { attributeFilter: ['data-a11y-mode', 'data-color-scheme'] });
        this.render();
    }

    disconnect() {
        this.observer.disconnect();
    }

    select(event) {
        applyColorScheme(document.documentElement, event.detail);
    }

    render() {
        const root = document.documentElement;
        const scheme = currentColorScheme(root);

        this.triggerTarget.setAttribute('aria-label', colorSchemeLabel(scheme, isA11yModeEnhanced(root), this.labelsValue));
        const icon = this.triggerTarget.querySelector('use');
        icon.setAttribute('href', icon.getAttribute('href').replace(/#.*$/, `#${ICONS[scheme]}`));
        for (const item of this.itemTargets) {
            item.setAttribute('aria-checked', String(item.dataset.buiMenuItem === scheme));
        }
    }
}
