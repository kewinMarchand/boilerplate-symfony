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
})
