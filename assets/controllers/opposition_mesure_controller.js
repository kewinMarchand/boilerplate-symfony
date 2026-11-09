import { Controller } from '@hotwired/stimulus';

import { isMesureRefusee, setOppositionMesure } from '../opposition_mesure.js';

export default class extends Controller {
    static targets = ['input', 'status'];
    static values = { refused: String, measured: String };

    connect() {
        this.render(isMesureRefusee());
    }

    change() {
        setOppositionMesure(this.inputTarget.checked);
        this.render(isMesureRefusee());
    }

    render(refusee) {
        this.inputTarget.checked = refusee;
        this.statusTarget.textContent = refusee ? this.refusedValue : this.measuredValue;
    }
}
