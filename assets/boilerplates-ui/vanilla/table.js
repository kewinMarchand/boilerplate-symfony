/* Généré par `make ui` depuis @kewinmarchand/boilerplates-ui, ne pas modifier à la main. */
/**
 * Tri d'un tableau par les boutons `[data-bui-sort]` de l'en-tête : trie les lignes du corps
 * sur le texte de la colonne, pose `aria-sort` sur la seule colonne triée et change l'icône.
 * Le tri suit la langue `locale`, par défaut celle de la page (`<html lang>`), sinon le français.
 */

/** @typedef {{ locale?: string }} SortOptions */

const ICONS = { ascending: 'arrow-up', descending: 'arrow-down', none: 'arrow-up-down' }

/** @param {HTMLButtonElement} button @param {SortOptions} [options] */
export const sortTable = (button, { locale = document.documentElement.lang || 'fr' } = {}) => {
  const header = /** @type {HTMLTableCellElement} */ (button.closest('th'))
  const table = /** @type {HTMLTableElement} */ (button.closest('table'))
  const body = table.tBodies[0]
  if (!body) return
  const direction = header.getAttribute('aria-sort') === 'ascending' ? 'descending' : 'ascending'
  const column = header.cellIndex

  for (const cell of table.tHead?.rows[0]?.cells ?? []) {
    cell.removeAttribute('aria-sort')
    cell.querySelector('use')?.setAttribute('href', iconHref(cell, ICONS.none))
  }
  header.setAttribute('aria-sort', direction)
  header.querySelector('use')?.setAttribute('href', iconHref(header, ICONS[direction]))

  const text = (/** @type {HTMLTableRowElement} */ row) => row.cells[column]?.textContent ?? ''
  const rows = [...body.rows].sort(
    (a, b) =>
      text(a).localeCompare(text(b), locale, { numeric: true }) *
      (direction === 'ascending' ? 1 : -1),
  )
  body.append(...rows)
}

/** @param {Element} cell @param {string} name */
const iconHref = (cell, name) =>
  `${(cell.querySelector('use')?.getAttribute('href') ?? '/icons.svg#').split('#')[0]}#${name}`

/**
 * Branche le tri des tableaux par délégation. Renvoie la fonction qui débranche.
 * @param {Document | HTMLElement} root
 * @param {SortOptions} [options]
 */
export const initTables = (root = document, options = {}) => {
  /** @param {Event} event */
  const handleClick = (event) => {
    const button =
      event.target instanceof Element && event.target.closest('.bui-table [data-bui-sort]')
    if (button instanceof HTMLButtonElement) sortTable(button, options)
  }
  root.addEventListener('click', handleClick)
  return () => root.removeEventListener('click', handleClick)
}
