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

const LABELS = { auto: 'système', light: 'clair', dark: 'sombre' };

// Le mode renforcé impose le clair : un autre choix reste mémorisé mais suspendu.
export const colorSchemeLabel = (scheme, enhanced) =>
    `Thème : ${LABELS[scheme]}${enhanced && scheme !== 'light' ? ", suspendu par l'accessibilité renforcée" : ''}`;
