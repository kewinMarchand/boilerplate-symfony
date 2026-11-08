/* Généré par `make ui` depuis @kewinmarchand/boilerplates-ui, ne pas modifier à la main. */
/**
 * Image en échec : `data-image-error` sur le `.bui-picture` dont l'`<img>` n'a pas chargé, retiré au
 * chargement d'une source. Le CSS cache alors l'image, à dimensions gardées, sur un fond neutre.
 */

/** @param {EventTarget | null} target */
const pictureOf = (target) =>
  target instanceof HTMLImageElement && target.parentElement?.matches('.bui-picture')
    ? target.parentElement
    : null

/** @param {Event} event */
const handleError = (event) => pictureOf(event.target)?.setAttribute('data-image-error', '')

/** @param {Event} event */
const handleLoad = (event) => pictureOf(event.target)?.removeAttribute('data-image-error')

/**
 * Branche le fond de remplacement des images en échec et marque celles qui ont déjà échoué
 * (rendu serveur chargé avant le JavaScript). Renvoie la fonction qui débranche.
 * @param {Document | HTMLElement} root
 */
export const initImageFallback = (root = document) => {
  for (const img of root.querySelectorAll('.bui-picture > img')) {
    if (img instanceof HTMLImageElement && img.complete && img.naturalWidth === 0)
      pictureOf(img)?.setAttribute('data-image-error', '')
  }
  root.addEventListener('error', handleError, true)
  root.addEventListener('load', handleLoad, true)
  return () => {
    root.removeEventListener('error', handleError, true)
    root.removeEventListener('load', handleLoad, true)
  }
}
