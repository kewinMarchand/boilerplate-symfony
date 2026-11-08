/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';

const STEPS = { ArrowLeft: -1, ArrowRight: 1 };

export default class extends Controller {
    static targets = ['main', 'image', 'thumb'];

    // Sans JavaScript, les vignettes sont des liens vers l'image en grand : elles deviennent des boutons. Une page restaurée du cache de Turbo a déjà ses boutons.
    connect() {
        this.thumbTargets
            .filter((thumb) => thumb.tagName === 'A')
            .forEach((link) => {
                const button = document.createElement('button');
                button.type = 'button';
                [...link.attributes].filter(({ name }) => name !== 'href').forEach(({ name, value }) => button.setAttribute(name, value));
                button.append(...link.childNodes);
                link.replaceWith(button);
            });
    }

    show(event) {
        event.preventDefault();
        this.select(this.thumbTargets.indexOf(event.currentTarget));
    }

    move(event) {
        const step = STEPS[event.key];
        if (!step) {
            return;
        }
        event.preventDefault();
        const count = this.thumbTargets.length;
        const index = (this.thumbTargets.indexOf(event.currentTarget) + step + count) % count;
        this.select(index);
        this.thumbTargets[index].focus();
    }

    select(index) {
        this.mainTarget.replaceChildren(this.imageTargets[index].content.cloneNode(true));
        this.thumbTargets.forEach((thumb, position) => {
            if (position === index) {
                thumb.setAttribute('aria-current', 'true');
            } else {
                thumb.removeAttribute('aria-current');
            }
        });
    }
}
