/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';

import { formatPrice, removeItem, setQuantity, totalPrice } from '../cart.js';
import { readCart, saveCart, subscribeToCart } from '../cart_storage.js';

const productPath = (slug) => `/produit/${encodeURIComponent(slug)}`;

// Contenu de la page panier, lu dans le navigateur. Les lignes ne sont reconstruites que si la liste des produits change :
// un champ de quantité en cours de saisie garde son focus.
export default class extends Controller {
    static targets = ['content', 'empty', 'summary', 'line'];

    connect() {
        this.renderedSlugs = null;
        this.unsubscribe = subscribeToCart(() => this.render());
        this.render();
    }

    disconnect() {
        this.unsubscribe();
    }

    changeQuantity(event) {
        const quantity = event.currentTarget.valueAsNumber;
        if (!Number.isNaN(quantity)) {
            saveCart(setQuantity(readCart(), event.currentTarget.closest('li').dataset.slug, quantity));
        }
    }

    // Le focus va au « Retirer » de la ligne qui prend la place, sinon de la précédente, sinon au panier vide :
    // sans cela, il retombe en haut de la page. Le rendu est synchrone, déclenché par saveCart.
    remove(event) {
        const row = event.currentTarget.closest('li');
        const index = [...row.parentElement.children].indexOf(row);
        saveCart(removeItem(readCart(), row.dataset.slug));

        const buttons = this.contentTarget.querySelectorAll('[data-cart-lines] > li [data-field="remove"]');
        (buttons[Math.min(index, buttons.length - 1)] ?? this.contentTarget.querySelector('[data-cart-empty]')).focus();
    }

    render() {
        const lines = readCart();
        if (lines.length === 0) {
            this.renderedSlugs = '';
            this.contentTarget.replaceChildren(this.emptyTarget.content.cloneNode(true));
            return;
        }

        const slugs = lines.map((line) => line.slug).join('\n');
        if (slugs !== this.renderedSlugs) {
            this.renderedSlugs = slugs;
            this.contentTarget.replaceChildren(this.summaryTarget.content.cloneNode(true));
            this.contentTarget.querySelector('[data-cart-lines]').replaceChildren(...lines.map((line) => this.createLine(line)));
        }

        for (const line of lines) {
            const row = this.contentTarget.querySelector(`li[data-slug="${CSS.escape(line.slug)}"]`);
            const input = row.querySelector('[data-field="quantity"]');
            if (input.valueAsNumber !== line.quantity) {
                input.value = String(line.quantity);
            }
            row.querySelector('[data-field="subtotal"]').textContent = formatPrice(line.price * line.quantity);
        }
        this.contentTarget.querySelector('[data-cart-total]').textContent = formatPrice(totalPrice(lines));
    }

    createLine(line) {
        const row = this.lineTarget.content.firstElementChild.cloneNode(true);
        const field = (name) => row.querySelector(`[data-field="${name}"]`);
        const inputId = `cart-quantity-${line.slug}`;

        row.dataset.slug = line.slug;
        field('name').href = productPath(line.slug);
        field('name').textContent = line.name;
        field('unit-price').textContent = `${formatPrice(line.price)} l’unité`;
        field('label').htmlFor = inputId;
        field('label').textContent = `Quantité de ${line.name}`;
        field('quantity').id = inputId;
        field('remove').setAttribute('aria-label', `Retirer ${line.name}`);

        return row;
    }
}
