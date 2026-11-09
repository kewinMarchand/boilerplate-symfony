/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';

import { formatPrice, lineIn, removeItem, setQuantity, totalPrice } from '../cart.js';
import { readCart, saveCart, subscribeToCart } from '../cart_storage.js';

// Contenu de la page panier, lu dans le navigateur. Les lignes ne sont reconstruites que si la liste des produits change :
// un champ de quantité en cours de saisie garde son focus.
export default class extends Controller {
    static targets = ['content', 'empty', 'summary', 'line'];
    // productPath : adresse d'une fiche dans la langue de la page, sans le slug ; labels : libellés de la page, passés par le HTML
    static values = { productPath: String, labels: Object };

    connect() {
        this.renderedIds = null;
        this.unsubscribe = subscribeToCart(() => this.render());
        this.render();
    }

    disconnect() {
        this.unsubscribe();
    }

    changeQuantity(event) {
        const quantity = event.currentTarget.valueAsNumber;
        if (!Number.isNaN(quantity)) {
            saveCart(setQuantity(readCart(), event.currentTarget.closest('li').dataset.id, quantity));
        }
    }

    // Le focus va au « Retirer » de la ligne qui prend la place, sinon de la précédente, sinon au panier vide :
    // sans cela, il retombe en haut de la page. Le rendu est synchrone, déclenché par saveCart.
    remove(event) {
        const row = event.currentTarget.closest('li');
        const index = [...row.parentElement.children].indexOf(row);
        saveCart(removeItem(readCart(), row.dataset.id));

        const buttons = this.contentTarget.querySelectorAll('[data-cart-lines] > li [data-field="remove"]');
        (buttons[Math.min(index, buttons.length - 1)] ?? this.contentTarget.querySelector('[data-cart-empty]')).focus();
    }

    render() {
        const lines = readCart();
        if (lines.length === 0) {
            this.renderedIds = '';
            this.contentTarget.replaceChildren(this.emptyTarget.content.cloneNode(true));
            return;
        }

        const ids = lines.map((line) => line.id).join('\n');
        if (ids !== this.renderedIds) {
            this.renderedIds = ids;
            this.contentTarget.replaceChildren(this.summaryTarget.content.cloneNode(true));
            this.contentTarget.querySelector('[data-cart-lines]').replaceChildren(...lines.map((line) => this.createLine(line)));
        }

        for (const line of lines) {
            const row = this.contentTarget.querySelector(`li[data-id="${CSS.escape(line.id)}"]`);
            const input = row.querySelector('[data-field="quantity"]');
            if (input.valueAsNumber !== line.quantity) {
                input.value = String(line.quantity);
            }
            row.querySelector('[data-field="subtotal"]').textContent = formatPrice(line.price * line.quantity, document.documentElement.lang);
        }
        this.contentTarget.querySelector('[data-cart-total]').textContent = formatPrice(totalPrice(lines), document.documentElement.lang);
    }

    createLine(line) {
        const row = this.lineTarget.content.firstElementChild.cloneNode(true);
        const field = (name) => row.querySelector(`[data-field="${name}"]`);
        const inputId = `cart-quantity-${line.id}`;
        const { slug, name } = lineIn(line, document.documentElement.lang);

        row.dataset.id = line.id;
        field('name').href = this.productPathValue + encodeURIComponent(slug);
        field('name').textContent = name;
        field('unit-price').textContent = this.labelsValue.unitPrice.replace('%price%', formatPrice(line.price, document.documentElement.lang));
        field('label').htmlFor = inputId;
        field('label').textContent = this.labelsValue.quantity.replace('%name%', name);
        field('quantity').id = inputId;
        field('remove').setAttribute('aria-label', this.labelsValue.remove.replace('%name%', name));

        return row;
    }
}
