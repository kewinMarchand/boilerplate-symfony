import { expect, test } from '@playwright/test'

import type { Page } from '@playwright/test'

const openFiltersOnMobile = async (page: Page, isMobile: boolean) => {
  if (isMobile) await page.getByTestId('catalog-filters-open').click()
}

const prices = async (page: Page) =>
  (await page.getByTestId('catalog-product-price').evaluateAll((elements) =>
    elements.map((element) => Number(element.getAttribute('data-price'))),
  )) as number[]

test.describe('Catalogue', () => {
  test('filtrer par exposition met à jour l’URL, le compteur et les puces', async ({ page, isMobile }) => {
    await page.goto('/catalogue')
    await expect(page.getByTestId('catalog-results-count')).toHaveText('24 produits')
    await expect(page.getByTestId('catalog-active-filters')).toHaveCount(0)

    await openFiltersOnMobile(page, isMobile)
    await page.getByTestId('catalog-filter-exposure-mi-ombre').check()

    await expect(page).toHaveURL(/\/catalogue\?exposition=mi-ombre$/)
    await expect(page.getByTestId('catalog-results-count')).toHaveText('7 produits')
    await expect(page.getByTestId('catalog-filter-exposure-mi-ombre')).toBeFocused()
    await expect(page.getByTestId('catalog-active-filter')).toHaveAccessibleName('Retirer le filtre : Mi-ombre')
  })

  test('filtrer par un attribut met à jour l’URL, le compteur et les puces', async ({ page, isMobile }) => {
    await page.goto('/catalogue?page=2')

    await openFiltersOnMobile(page, isMobile)
    const caduc = page.getByTestId('catalog-filter-attribute-feuillage-caduc')
    await expect(caduc).toHaveAccessibleName('Caduc (2)')
    await caduc.check()

    await expect(page).toHaveURL(/\/catalogue\?attr-feuillage=caduc$/)
    await expect(page.getByTestId('catalog-results-count')).toHaveText('2 produits')
    await expect(page.getByTestId('catalog-active-filter')).toHaveAccessibleName('Retirer le filtre : Feuillage : Caduc')

    await page.getByTestId('catalog-filter-attribute-feuillage-persistant').check()
    await expect(page).toHaveURL(/\/catalogue\?attr-feuillage=persistant&attr-feuillage=caduc$/)
    await expect(page.getByTestId('catalog-results-count')).toHaveText('8 produits')

    if (isMobile) await page.keyboard.press('Escape')
    await page.getByTestId('catalog-clear-filters').click()
    await expect(page).toHaveURL(/\/catalogue$/)
  })

  test('sans attribut à choix dans la branche, aucune facette d’attribut', async ({ page }) => {
    await page.goto('/catalogue/plantes-exterieur/arbustes-a-fleurs')

    await expect(page.getByTestId('catalog-results-count')).toHaveText('6 produits')
    await expect(page.locator('[data-testid^="catalog-filter-attribute-"]')).toHaveCount(0)
  })

  test('retirer une puce et tout effacer', async ({ page }) => {
    await page.goto('/catalogue?exposition=soleil&taille=M')
    await expect(page.getByTestId('catalog-active-filter')).toHaveCount(2)

    await page.getByTestId('catalog-active-filter').first().click()
    await expect(page).toHaveURL(/\/catalogue\?taille=M$/)
    await expect(page.getByTestId('catalog-active-filter')).toHaveCount(1)

    await page.getByTestId('catalog-clear-filters').click()
    await expect(page).toHaveURL(/\/catalogue$/)
    await expect(page.getByTestId('catalog-active-filters')).toHaveCount(0)
    await expect(page.getByRole('heading', { level: 1 })).toBeFocused()
  })

  test('trier par prix croissant', async ({ page }) => {
    await page.goto('/catalogue/plantes-interieur')
    await page.getByTestId('catalog-sort').selectOption('prix-asc')

    await expect(page).toHaveURL(/tri=prix-asc/)
    await expect.poll(async () => (await prices(page)).slice(0, 2)).toEqual([1290, 1590])
  })

  test('paginer conserve les filtres et revient en arrière', async ({ page }) => {
    await page.goto('/catalogue?tri=nom')
    const pagination = page.getByTestId('catalog-pagination')

    await pagination.getByRole('link', { name: 'Page 2' }).click()
    await expect(page).toHaveURL(/\/catalogue\?tri=nom&page=2$/)
    await expect(pagination.locator('[aria-current="page"]')).toHaveText('Page 2')
    await expect(page.getByTestId('catalog-product')).toHaveCount(12)

    await page.getByTestId('catalog-pagination').getByRole('link', { name: 'Précédent' }).click()
    await expect(page).toHaveURL(/\/catalogue\?tri=nom$/)
    await expect(page.getByTestId('catalog-pagination').locator('[aria-current="page"]')).toHaveText('Page 1')
  })

  test('la vue liste est conservée quand on filtre', async ({ page, isMobile }) => {
    await page.goto('/catalogue')
    await page.getByTestId('catalog-view-list').click()
    await expect(page).toHaveURL(/vue=liste/)
    await expect(page.getByTestId('catalog-view-list')).toHaveAttribute('aria-current', 'page')

    await openFiltersOnMobile(page, isMobile)
    await page.getByTestId('catalog-filter-in-stock').check()
    await expect(page).toHaveURL(/\/catalogue\?en_stock=1&vue=liste$/)
  })

  test('affiche un état vide amical', async ({ page }) => {
    await page.goto('/catalogue?prix_max=1')

    await expect(page.getByTestId('catalog-empty')).toContainText('Aucun produit ne correspond à ces filtres.')
    await expect(page.getByTestId('catalog-product')).toHaveCount(0)
  })

  test('les filtres fonctionnent sans JavaScript avec le formulaire GET', async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false })
    const page = await context.newPage()
    await page.goto('/catalogue')

    await page.getByTestId('catalog-filter-size-L').check()
    await page.getByRole('button', { name: 'Appliquer les filtres' }).click()

    await expect(page).toHaveURL(/\/catalogue\?taille=L$/)
    await expect(page.getByTestId('catalog-results-count')).toHaveText('9 produits')
    await context.close()
  })

  test('la facette d’attribut fonctionne sans JavaScript avec le formulaire GET', async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false })
    const page = await context.newPage()
    await page.goto('/catalogue')

    await page.getByTestId('catalog-filter-attribute-feuillage-persistant').check()
    await page.getByRole('button', { name: 'Appliquer les filtres' }).click()

    await expect(page).toHaveURL(/\/catalogue\?attr-feuillage=persistant$/)
    await expect(page.getByTestId('catalog-results-count')).toHaveText('6 produits')
    await context.close()
  })
})

test.describe('Catalogue sur mobile', () => {
  test('le panneau de filtres s’ouvre, se ferme à Échap et rend le focus', async ({ page, isMobile }) => {
    test.skip(!isMobile, 'le panneau modal n’existe que sous 1024 px')
    await page.goto('/catalogue')
    const opener = page.getByTestId('catalog-filters-open')
    const panel = page.getByTestId('catalog-filters')

    await expect(panel).toBeHidden()
    await opener.click()
    await expect(panel).toBeVisible()
    await expect(panel).toHaveAttribute('role', 'dialog')
    await expect(opener).toHaveAttribute('aria-expanded', 'true')

    await page.keyboard.press('Escape')
    await expect(panel).toBeHidden()
    await expect(opener).toBeFocused()
    await expect(opener).toHaveAttribute('aria-expanded', 'false')
  })

  test('le panneau reste ouvert pendant le filtrage', async ({ page, isMobile }) => {
    test.skip(!isMobile, 'le panneau modal n’existe que sous 1024 px')
    await page.goto('/catalogue')
    await page.getByTestId('catalog-filters-open').click()
    await page.getByTestId('catalog-filter-size-S').check()

    await expect(page).toHaveURL(/taille=S/)
    await expect(page.getByTestId('catalog-filters')).toBeVisible()
    await expect(page.getByRole('button', { name: 'Voir les 6 produits' })).toBeVisible()
  })
})

test.describe('Catalogue, états de l’API', () => {
  test('erreur de l’API : message et relance', async ({ page }) => {
    await page.setExtraHTTPHeaders({ 'X-Scenario': 'error' })
    await page.goto('/catalogue')

    await expect(page.getByTestId('catalog-error')).toBeVisible()
    await expect(page.getByTestId('catalog-retry')).toBeVisible()
    await expect(page.getByTestId('catalog-product')).toHaveCount(0)
  })

  test('liste vide de l’API : message amical', async ({ page }) => {
    await page.setExtraHTTPHeaders({ 'X-Scenario': 'empty' })
    await page.goto('/catalogue')

    await expect(page.getByTestId('catalog-empty')).toBeVisible()
    await expect(page.getByTestId('catalog-error')).toHaveCount(0)
  })
})
