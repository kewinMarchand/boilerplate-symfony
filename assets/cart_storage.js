import { MAX_QUANTITY } from './cart.js';

export const CART_KEY = 'panier';
const CHANGE_EVENT = 'panier:change';

const isLine = (line) =>
    typeof line === 'object' &&
    line !== null &&
    typeof line.slug === 'string' &&
    line.slug !== '' &&
    typeof line.name === 'string' &&
    line.name !== '' &&
    Number.isInteger(line.price) &&
    line.price >= 0 &&
    Number.isInteger(line.quantity) &&
    line.quantity >= 1 &&
    line.quantity <= MAX_QUANTITY;

/** Panier enregistré ; vide si l'entrée est absente, illisible ou invalide. */
export const parseCart = (raw) => {
    if (raw === null) {
        return [];
    }
    try {
        const lines = JSON.parse(raw);
        return Array.isArray(lines) && lines.every(isLine)
            ? lines.map(({ slug, name, price, quantity }) => ({ slug, name, price, quantity }))
            : [];
    } catch {
        return [];
    }
};

// Garde le panier quand le stockage refuse l'écriture (navigation privée, quota) : il vaut alors pour la page en cours.
let memory = null;

const readStorage = () => {
    try {
        return globalThis.localStorage.getItem(CART_KEY);
    } catch {
        return null;
    }
};

export const readCart = () => parseCart(memory ?? readStorage());

export const saveCart = (lines) => {
    const raw = JSON.stringify(lines);
    try {
        globalThis.localStorage.setItem(CART_KEY, raw);
        memory = null;
    } catch {
        memory = raw;
    }
    window.dispatchEvent(new Event(CHANGE_EVENT));
};

/** Prévient des changements de cet onglet et des autres (événement `storage`) ; rend la fonction de désabonnement. */
export const subscribeToCart = (onChange) => {
    const onStorage = (event) => {
        if (event.key === CART_KEY || event.key === null) {
            onChange();
        }
    };
    window.addEventListener(CHANGE_EVENT, onChange);
    window.addEventListener('storage', onStorage);

    return () => {
        window.removeEventListener(CHANGE_EVENT, onChange);
        window.removeEventListener('storage', onStorage);
    };
};
