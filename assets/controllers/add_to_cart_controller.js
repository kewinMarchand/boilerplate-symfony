/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';

import { addItem } from '../cart.js';
import { readCart, saveCart } from '../cart_storage.js';

// Vider puis remplir la région : un lecteur d'écran ne relit pas un texte identique
const ANNOUNCE_DELAY_MS = 100;

export default class extends Controller {
    static targets = ['template', 'status'];
    static values = { item: Object, cartUrl: String };

    // Le bouton n'existe qu'avec JavaScript. Tout ce qui suit le gabarit est remplacé : une page restaurée du cache de Turbo le contient déjà.
    connect() {
        this.element.replaceChildren(this.templateTarget, this.templateTarget.content.cloneNode(true));
    }

    add() {
        saveCart(addItem(readCart(), this.itemValue));

        const link = document.createElement('a');
        link.className = 'bui-link';
        link.href = this.cartUrlValue;
        link.textContent = 'Voir le panier';
        this.statusTarget.replaceChildren();
        setTimeout(() => this.statusTarget.replaceChildren(`${this.itemValue.name} ajouté au panier. `, link), ANNOUNCE_DELAY_MS);
    }
}
