import AxeBuilder from '@axe-core/playwright'
import { expect, test } from '@playwright/test'

import { useA11yMode } from './a11yMode'
import { A11Y_MODES } from './routes'

// Vente en ligne coupée (@identite) : la fausse API de make test-e2e (tests/e2e/fixtures/fake-api.js) sert la fixture
// site-identity-sans-vente selon X-Scenario, sans toucher aux données de l'API réelle.
const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']
const PRODUCT = '/produit/monstera-deliciosa'
const INQUIRY = '/contact?produit=monstera-deliciosa'

test.describe('Vente en ligne active', () => {
  test('la fiche garde le bouton d’ajout au panier, sans lien de demande', async ({ page }) => {
    await page.goto(PRODUCT)

    await expect(page.getByTestId('add-to-cart')).toBeVisible()
    await expect(page.getByTestId('product-price')).toBeVisible()
    await expect(page.getByTestId('product-inquiry')).toHaveCount(0)
    await expect(page.getByTestId('cart-link')).toBeVisible()
  })

  test('une demande de renseignement reste possible par l’URL du contact', async ({ page }) => {
    await page.goto(INQUIRY)

    await expect(page.getByTestId('contact-product')).toHaveText('Votre demande concerne : Monstera deliciosa')
  })

  test('un produit inconnu laisse un formulaire de contact simple', async ({ page }) => {
    await page.goto('/contact?produit=produit-inconnu')

    await expect(page.getByTestId('contact-submit')).toBeVisible()
    await expect(page.getByTestId('contact-product')).toHaveCount(0)
  })
})

test.describe('Vente en ligne coupée @identite', () => {
  test.beforeEach(async ({ page }) => {
    await page.setExtraHTTPHeaders({ 'X-Scenario': 'sans-vente' })
  })

  test('le catalogue n’affiche ni prix, ni filtre de prix, ni tri par prix', async ({ page }) => {
    await page.goto('/catalogue')

    await expect(page.getByTestId('catalog-product')).toHaveCount(12)
    await expect(page.getByTestId('catalog-product-price')).toHaveCount(0)
    await expect(page.getByTestId('catalog-filter-price-min')).toHaveCount(0)
    await expect(page.getByTestId('catalog-filter-price-max')).toHaveCount(0)
    await expect(page.getByTestId('catalog-sort').locator('option')).toHaveText(['Pertinence', 'Nom'])
  })

  test('les paramètres de prix de l’URL sont ignorés', async ({ page }) => {
    await page.goto('/catalogue?prix_min=10&tri=prix-asc')

    await expect(page).toHaveURL(/\/catalogue$/)
    await expect(page.getByTestId('catalog-active-filters')).toHaveCount(0)
  })

  test('le panier disparaît : pas de lien dans le header, /panier en 404', async ({ page }) => {
    await page.goto('/')
    await expect(page.getByTestId('layout-logo')).toBeVisible()
    await expect(page.getByTestId('cart-link')).toHaveCount(0)

    const response = await page.goto('/panier')
    expect(response?.status()).toBe(404)
  })

  test('le contenu du panier n’est ni lu ni effacé', async ({ page }) => {
    const stored = JSON.stringify([{ slug: 'monstera-deliciosa', name: 'Monstera deliciosa', price: 3490, quantity: 2 }])
    await page.addInitScript((value) => window.localStorage.setItem('panier', value), stored)
    await page.goto(PRODUCT)
    await expect(page.getByTestId('product-inquiry')).toBeVisible()

    expect(await page.evaluate(() => window.localStorage.getItem('panier'))).toBe(stored)
  })

  test('la fiche propose une demande de renseignement à la place du prix et du panier', async ({ page }) => {
    await page.goto(PRODUCT)

    const inquiry = page.getByTestId('product-inquiry')
    await expect(inquiry).toHaveAccessibleName('Demande de renseignement sur Monstera deliciosa')
    expect((await inquiry.boundingBox())?.height).toBeGreaterThanOrEqual(44)
    await expect(page.getByTestId('product-price')).toHaveCount(0)
    await expect(page.getByTestId('add-to-cart')).toHaveCount(0)
    const jsonLd = await page.locator('script[type="application/ld+json"]').allTextContents()
    const product = jsonLd.map((json) => JSON.parse(json)).find((data) => data['@type'] === 'Product')
    expect(product).toBeDefined()
    expect(product).not.toHaveProperty('offers')
  })

  test('un produit en rupture de stock garde la demande de renseignement', async ({ page }) => {
    await page.goto('/produit/monstera-thai-constellation')

    await expect(page.getByTestId('product-inquiry')).toBeVisible()
  })

  test('la demande mène au contact, nomme le produit et l’envoie avec le message', async ({ page }) => {
    await page.goto(PRODUCT)
    await page.getByTestId('product-inquiry').click()

    await expect(page).toHaveURL(new RegExp(`${INQUIRY.replace('?', '\\?')}$`))
    await expect(page.getByTestId('contact-product')).toHaveText('Votre demande concerne : Monstera deliciosa')
    await page.getByTestId('contact-name').fill('Ada')
    await page.getByTestId('contact-email').fill('ada@exemple.fr')
    await page.getByTestId('contact-message').fill('Bonjour, ce Monstera est-il disponible en taille L ?')

    const submitted = page.waitForRequest((request) => request.method() === 'POST' && new URL(request.url()).pathname === '/contact')
    await page.getByTestId('contact-submit').click()

    expect(new URLSearchParams((await submitted).postData() ?? '').get('contact[product]')).toBe('monstera-deliciosa')
    await expect(page.getByTestId('contact-success')).toContainText('Votre demande de renseignement a bien été envoyée.')
  })

  for (const mode of A11Y_MODES) {
    for (const route of [PRODUCT, INQUIRY]) {
      test(`la page ${route} en mode ${mode} ne présente aucune violation axe`, async ({ page }) => {
        await useA11yMode(page, mode)
        await page.goto(route)
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible()

        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([])
      })
    }
  }
})

test.describe('Vente en ligne coupée, sans JavaScript @identite', () => {
  test.use({ javaScriptEnabled: false })

  test('la demande de renseignement reste un lien', async ({ page }) => {
    await page.setExtraHTTPHeaders({ 'X-Scenario': 'sans-vente' })
    await page.goto(PRODUCT)

    await expect(page.getByTestId('product-inquiry')).toHaveAttribute('href', INQUIRY)
  })
})
