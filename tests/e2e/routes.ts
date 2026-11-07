export const ROUTES = [
  '/',
  '/catalogue',
  '/catalogue/plantes-interieur/feuillages',
  '/taches',
  '/contact',
  '/mentions-legales',
  '/donnees-personnelles',
  '/accessibilite',
  '/plan-du-site',
  '/blog/le-mode-accessibilite-renforcee',
] as const

export const A11Y_MODES = ['standard', 'dark', 'enhanced'] as const

export type A11yMode = (typeof A11Y_MODES)[number]
