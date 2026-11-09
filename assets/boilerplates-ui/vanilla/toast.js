/* Généré par `make ui` depuis @kewinmarchand/boilerplates-ui, ne pas modifier à la main. */
/**
 * File de notifications. Chaque toast reste affiché jusqu'à sa fermeture : pas de disparition
 * automatique (WCAG 2.2.1). `role="alert"` pour un avertissement ou une erreur, `status` sinon.
 */

/** @typedef {'info' | 'success' | 'warning' | 'error'} ToastVariant */

/** @type {Record<ToastVariant, { icon: string, prefix: string, role: 'status' | 'alert' }>} */
const VARIANTS = {
  info: { icon: 'info', prefix: 'Information :', role: 'status' },
  success: { icon: 'circle-check', prefix: 'Succès :', role: 'status' },
  warning: { icon: 'triangle-alert', prefix: 'Attention :', role: 'alert' },
  error: { icon: 'circle-alert', prefix: 'Erreur :', role: 'alert' },
}

const SVG_NS = 'http://www.w3.org/2000/svg'
let sprite = '/icons.svg'
let counter = 0

/** @param {string} url */
export const setToastIconSprite = (url) => {
  sprite = url
}

/** @param {string} name @param {string} [className] */
const icon = (name, className) => {
  const svg = document.createElementNS(SVG_NS, 'svg')
  svg.setAttribute('class', className ? `bui-icon ${className}` : 'bui-icon')
  svg.setAttribute('aria-hidden', 'true')
  const use = document.createElementNS(SVG_NS, 'use')
  use.setAttribute('href', `${sprite}#${name}`)
  svg.append(use)
  return svg
}

/**
 * Ajoute un toast en bas de la file et renvoie son élément.
 * `prefix` (préfixe masqué, celui de la variante par défaut) et `closeLabel` (nom du bouton de fermeture)
 * remplacent les libellés français.
 * @param {HTMLElement} toaster élément `[data-bui-toaster]`
 * @param {{ message: string, variant?: ToastVariant, prefix?: string, closeLabel?: string }} options
 */
export const showToast = (
  toaster,
  {
    message,
    variant = 'info',
    prefix = VARIANTS[variant].prefix,
    closeLabel = 'Fermer la notification',
  },
) => {
  const { role } = VARIANTS[variant]
  const toast = document.createElement('div')
  toast.className = `bui-toast bui-toast--${variant}`
  toast.id = `bui-toast-${++counter}`
  toast.setAttribute('role', role)

  const text = document.createElement('p')
  text.className = 'bui-toast__message'
  const hidden = document.createElement('span')
  hidden.className = 'bui-visually-hidden'
  hidden.textContent = `${prefix} `
  text.append(hidden, message)

  const close = document.createElement('button')
  close.type = 'button'
  close.className = 'bui-toast__close'
  close.setAttribute('aria-label', closeLabel)
  close.setAttribute('data-bui-toast-close', '')
  close.append(icon('x'))

  toast.append(icon(VARIANTS[variant].icon, 'bui-toast__icon'), text, close)
  toaster.append(toast)
  return toast
}

/** @param {Event} event */
const handleClick = (event) => {
  if (!(event.target instanceof Element)) return
  if (event.target.closest('[data-bui-toast-close]')) event.target.closest('.bui-toast')?.remove()
}

/**
 * Branche la fermeture des toasts par délégation. Renvoie la fonction qui débranche.
 * @param {Document | HTMLElement} root
 */
export const initToasts = (root = document) => {
  root.addEventListener('click', handleClick)
  return () => root.removeEventListener('click', handleClick)
}
