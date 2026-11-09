export const COLOR_SCHEME_KEY = 'color-scheme';
export const COLOR_SCHEMES = ['auto', 'light', 'dark'];

export const currentColorScheme = (root) =>
    COLOR_SCHEMES.includes(root.dataset.colorScheme) ? root.dataset.colorScheme : 'auto';

export const applyColorScheme = (root, scheme) => {
    root.dataset.colorScheme = scheme;

    try {
        globalThis.localStorage.setItem(COLOR_SCHEME_KEY, scheme);
    } catch {
        // Stockage indisponible (navigation privée, cookies bloqués) : le choix vaut pour la page.
    }

    return scheme;
};

// Le mode renforcé impose le clair : un autre choix reste mémorisé mais suspendu.
// labels : libellés de la page, { auto, light, dark, suspended }, passés par le HTML dans sa langue.
export const colorSchemeLabel = (scheme, enhanced, labels) =>
    `${labels[scheme]}${enhanced && scheme !== 'light' ? labels.suspended : ''}`;
