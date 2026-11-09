// Panier du navigateur (section 11 ter de la spec commune) : lignes { id, slug, name, price, quantity, translations }, identifiées
// par le produit, prix en centimes relevé à l'ajout. slug et name dans la langue de l'ajout, translations ({ locale, slug, name })
// dans chaque langue du site, vide sur un site monolingue.
export const MAX_QUANTITY = 99;

const clampQuantity = (quantity) => Math.min(MAX_QUANTITY, Math.max(1, Math.trunc(quantity)));

export const addItem = (lines, item) =>
    lines.some((line) => line.id === item.id)
        ? lines.map((line) => (line.id === item.id ? { ...line, quantity: clampQuantity(line.quantity + 1) } : line))
        : [...lines, { ...item, quantity: 1 }];

export const setQuantity = (lines, id, quantity) =>
    lines.map((line) => (line.id === id ? { ...line, quantity: clampQuantity(quantity) } : line));

export const removeItem = (lines, id) => lines.filter((line) => line.id !== id);

/** Slug et nom de la ligne dans la langue de la page, ceux de la langue de l'ajout à défaut. */
export const lineIn = (line, locale) => {
    const { slug, name } = line.translations.find((translation) => translation.locale === locale) ?? line;
    return { slug, name };
};

export const countItems = (lines) => lines.reduce((count, line) => count + line.quantity, 0);

export const totalPrice = (lines) => lines.reduce((total, line) => total + line.price * line.quantity, 0);

// locale : langue de la page (<html lang>), le français par défaut
export const formatPrice = (cents, locale = 'fr-FR') =>
    new Intl.NumberFormat(locale, { style: 'currency', currency: 'EUR' }).format(cents / 100);
