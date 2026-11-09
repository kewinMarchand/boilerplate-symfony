/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';

import { countItems } from '../cart.js';
import { readCart, subscribeToCart } from '../cart_storage.js';

const span = (text, className) => {
    const element = document.createElement('span');
    element.className = className;
    element.textContent = text;
    return element;
};

// Nom accessible « Panier, 3 articles » composé du texte visible et de compléments masqués : axe refuse un aria-label qui ne contient pas le texte affiché.
export default class extends Controller {
    static targets = ['count'];
    static values = { items: Object };

    connect() {
        this.unsubscribe = subscribeToCart(() => this.render());
        this.render();
    }

    disconnect() {
        this.unsubscribe();
    }

    render() {
        const count = countItems(readCart());
        if (count === 0) {
            this.countTarget.replaceChildren();
            return;
        }

        const badge = span(String(count), 'cart-link__count');
        badge.dataset.testid = 'cart-count';
        this.countTarget.replaceChildren(
            span(', ', 'bui-visually-hidden'),
            badge,
            span(this.itemsValue[new Intl.PluralRules(document.documentElement.lang).select(count)] ?? this.itemsValue.other, 'bui-visually-hidden'),
        );
    }
}
