import { expect, test } from '@playwright/test'

const PRODUCT = '/produit/monstera-deliciosa'

test.describe('Fiche produit', () => {
  test('s’ouvre depuis une carte du catalogue', async ({ page }) => {
    await page.goto('/catalogue')

    const card = page.getByTestId('catalog-product').first()
    const name = (await card.locator('h2').textContent()) ?? ''
    await card.getByTestId('catalog-product-link').click()

    await expect(page).toHaveURL(/\/produit\/[a-z0-9-]+$/)
    await expect(page.getByTestId('product-title')).toHaveText(name)
  })

  test('affiche le nom, le prix, la description, les caractéristiques, l’image de l’API et le chemin de sa catégorie', async ({
    page,
  }) => {
    await page.goto(PRODUCT)

    await expect(page.getByTestId('product-title')).toHaveText('Monstera deliciosa')
    await expect(page.getByTestId('product-price')).toHaveText(/34,90\s€/)
    const description = page.getByTestId('product-description').locator('p')
    await expect(description).toHaveCount(2)
    await expect(description.first()).toContainText('Grandes feuilles vert profond')
    await expect(page.getByTestId('product-page').locator('dd').first()).toHaveText('Mi-ombre')
    const image = page.getByTestId('product-page').locator('img').first()
    await expect(image).toHaveAttribute('fetchpriority', 'high')
    await expect
      .poll(() => image.evaluate((element: HTMLImageElement) => element.naturalWidth))
      .toBeGreaterThan(0)
    const breadcrumb = page.getByTestId('layout-breadcrumb')
    await expect(breadcrumb).toContainText('Feuillages')
    await expect(breadcrumb.getByRole('link', { name: 'Monstera', exact: true })).toHaveAttribute(
      'href',
      '/catalogue/plantes-interieur/feuillages/monstera',
    )
  })

  test('affiche ses attributs dans une liste de définitions', async ({ page }) => {
    await page.goto(PRODUCT)

    const attributes = page.getByTestId('product-attributes')
    await expect(attributes.locator('dt')).toHaveText(['Feuillage', 'Hauteur adulte'])
    await expect(attributes.locator('dd')).toHaveText(['Persistant', /^300\scm$/])
  })

  test('n’affiche aucune liste d’attributs sans attribut renseigné', async ({ page }) => {
    await page.goto('/produit/monstera-thai-constellation')

    await expect(page.getByTestId('product-title')).toHaveText('Monstera Thai Constellation')
    await expect(page.getByTestId('product-attributes')).toHaveCount(0)
  })

  test('expose ses données structurées de produit', async ({ page }) => {
    await page.goto(PRODUCT)

    const data = (await page.locator('script[type="application/ld+json"]').allTextContents()).map(
      (text) =>
        JSON.parse(text) as { '@type': string; description?: string; offers?: { price: string } },
    )
    expect(data.map((item) => item['@type'])).toEqual(
      expect.arrayContaining(['Product', 'BreadcrumbList']),
    )
    const product = data.find((item) => item['@type'] === 'Product')
    expect(product?.offers?.price).toBe('34.90')
    expect(product?.description).toMatch(/^Grandes feuilles vert profond.+aide à grimper\.$/)
  })

  test('propose les autres produits de sa catégorie, sans lui-même', async ({ page }) => {
    await page.goto(PRODUCT)

    const similar = page.getByTestId('product-similar')
    await expect(similar.getByRole('heading', { level: 2 })).toHaveText('Produits similaires')
    const links = similar.getByTestId('carousel-slide-link')
    await expect(links).toHaveText(['Monstera adansonii', 'Monstera Thai Constellation'])
    await expect(similar).not.toContainText('Monstera deliciosa')
    await links.first().click()

    await expect(page).toHaveURL('/produit/monstera-adansonii')
    await expect(page.getByTestId('product-title')).toHaveText('Monstera adansonii')
  })

  test('répond 404 pour un produit inconnu', async ({ page }) => {
    const response = await page.goto('/produit/produit-inconnu')

    expect(response?.status()).toBe(404)
  })

  test('galerie : vignettes en boutons, la première affichée', async ({ page }) => {
    await page.goto(PRODUCT)

    const thumbs = page.getByTestId('product-gallery').getByTestId('product-gallery-thumb')
    await expect(thumbs).toHaveCount(3)
    await expect(thumbs.first()).toHaveAccessibleName('Voir l’image 1 sur 3')
    await expect(thumbs.first()).toHaveAttribute('aria-current', 'true')
    await expect(page.getByTestId('product-gallery').getByRole('button')).toHaveCount(3)
    await expect(thumbs.first().locator('img')).toHaveAttribute('loading', 'lazy')
    await expect(page.getByTestId('product-gallery').locator('img').first()).toHaveAttribute('fetchpriority', 'high')
  })

  test('galerie : un clic sur une vignette remplace l’image affichée', async ({ page }) => {
    await page.goto(PRODUCT)

    const thumbs = page.getByTestId('product-gallery-thumb')
    const main = page.getByTestId('product-gallery').locator('img').first()
    await expect(main).toHaveAttribute('src', /01272a8b6d3f8221cfe5a2a36aefcd35/)
    await thumbs.nth(1).click()

    await expect(main).toHaveAttribute('src', /a32d6e246a7553a3f0304c6fc434a220/)
    await expect(thumbs.nth(1)).toHaveAttribute('aria-current', 'true')
    await expect(thumbs.first()).not.toHaveAttribute('aria-current')
  })

  test('galerie : les flèches du clavier passent d’une vignette à l’autre', async ({ page }) => {
    await page.goto(PRODUCT)

    const thumbs = page.getByTestId('product-gallery-thumb')
    const main = page.getByTestId('product-gallery').locator('img').first()
    await expect(page.getByTestId('product-gallery').getByRole('button')).toHaveCount(3)
    await thumbs.first().focus()
    await page.keyboard.press('ArrowRight')

    await expect(thumbs.nth(1)).toBeFocused()
    await expect(thumbs.nth(1)).toHaveAttribute('aria-current', 'true')
    await expect(main).toHaveAttribute('src', /a32d6e246a7553a3f0304c6fc434a220/)

    await page.keyboard.press('ArrowLeft')
    await page.keyboard.press('ArrowLeft')
    await expect(thumbs.nth(2)).toBeFocused()
    await expect(main).toHaveAttribute('src', /b1a780cf9c0bdcfcce00f31e0056be87/)
  })

  test('n’affiche aucune galerie sans image supplémentaire', async ({ page }) => {
    await page.goto('/produit/monstera-thai-constellation')

    await expect(page.getByTestId('product-title')).toHaveText('Monstera Thai Constellation')
    await expect(page.getByTestId('product-gallery')).toHaveCount(0)
    await expect(page.getByTestId('product-page').locator('img').first()).toHaveAttribute('fetchpriority', 'high')
  })
})

test.describe('Fiche produit sans JavaScript', () => {
  test.use({ javaScriptEnabled: false })

  test('les vignettes de la galerie sont des liens vers l’image en grand', async ({ page }) => {
    await page.goto(PRODUCT)

    const thumbs = page.getByTestId('product-gallery-thumb')
    await expect(thumbs).toHaveCount(3)
    await expect(page.getByTestId('product-gallery').getByRole('link')).toHaveCount(3)
    await expect(thumbs.nth(1)).toHaveAttribute('href', /a32d6e246a7553a3f0304c6fc434a220-800\.webp$/)
  })
})

test.describe('Fiche produit avec métadonnées saisies @identite', () => {
  test('le titre et la description saisis remplacent ceux calculés', async ({ page }) => {
    await page.setExtraHTTPHeaders({ 'X-Scenario': 'metas' })
    await page.goto(PRODUCT)

    await expect(page).toHaveTitle('Titre saisi dans l’admin')
    await expect(page.locator('meta[name="description"]')).toHaveAttribute('content', /^Description saisie dans l’admin/)
    await expect(page.locator('meta[property="og:title"]')).toHaveAttribute('content', 'Titre saisi dans l’admin')
    await expect(page.getByTestId('product-title')).toHaveText('Monstera deliciosa')
  })
})

test.describe('Fiche produit sans métadonnées saisies', () => {
  test('garde le titre et la description calculés', async ({ page }) => {
    await page.goto(PRODUCT)

    await expect(page).toHaveTitle('Monstera deliciosa · Boilerplate')
    await expect(page.locator('meta[name="description"]')).toHaveAttribute('content', /^Monstera deliciosa : exposition mi-ombre/)
  })
})
