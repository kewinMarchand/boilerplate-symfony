import AxeBuilder from '@axe-core/playwright'
import { expect, test } from '@playwright/test'

import type { Page } from '@playwright/test'

// Les cas « avec logo » et « avec couleurs » (@identite) tournent contre la fausse API de make test-e2e
// (tests/e2e/fixtures/fake-api.js), qui sert les fixtures site-identity-logo et site-identity-couleurs selon X-Scenario.
const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']

const primaryOf = (page: Page) =>
  page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--bui-color-primary').trim())

const remember = (page: Page, key: 'color-scheme' | 'a11y-mode', value: string) =>
  page.addInitScript(([k, v]) => window.localStorage.setItem(k, v), [key, value])

test.describe('Identité du site lue dans l’API', () => {
  test('sans logo, le header affiche le pictogramme et le nom de l’API, repris dans le titre', async ({ page }) => {
    await page.goto('/contact')

    const logo = page.getByTestId('layout-logo')
    await expect(logo).toHaveText('Boilerplate')
    await expect(logo.locator('img')).toHaveAttribute('alt', '')
    await expect(page.getByTestId('layout-logo-image')).toHaveCount(0)
    await expect(page).toHaveTitle('Contact · Boilerplate')
  })

  test('sans couleurs saisies, aucun bloc de style et les jetons de la bibliothèque', async ({ page }) => {
    await page.goto('/')

    await expect(page.getByTestId('site-theme-style')).toHaveCount(0)
    expect(await primaryOf(page)).toBe('#1d4ed8')
  })

  test('API en erreur, la config du site sert de repli et la page reste servie', async ({ page }) => {
    await page.setExtraHTTPHeaders({ 'X-Scenario': 'error' })
    const response = await page.goto('/contact')

    expect(response?.status()).toBe(200)
    await expect(page.getByTestId('layout-logo')).toHaveText('Boilerplate Symfony')
    await expect(page).toHaveTitle('Contact · Boilerplate Symfony')
  })
})

test.describe('Identité du site avec logo @identite', () => {
  test('le logo remplace le pictogramme et le nom, à 40 px de haut', async ({ page }) => {
    await page.setExtraHTTPHeaders({ 'X-Scenario': 'identite-logo' })
    await page.goto('/contact')

    const image = page.getByTestId('layout-logo-image')
    await expect(page.getByTestId('layout-logo')).toHaveAccessibleName('Oraki')
    await expect(image).toHaveAttribute('alt', 'Oraki')
    await expect(image).toHaveAttribute('fetchpriority', 'high')
    expect((await image.boundingBox())?.height).toBe(40)
    expect(await image.evaluate((img: HTMLImageElement) => img.complete && img.naturalWidth > 0)).toBe(true)
    await expect(page.getByTestId('layout-logo').locator('span')).toHaveCount(0)
    await expect(page).toHaveTitle('Contact · Oraki')
  })
})

test.describe('Identité du site avec couleurs @identite', () => {
  test.beforeEach(async ({ page }) => {
    await page.setExtraHTTPHeaders({ 'X-Scenario': 'identite-couleurs' })
  })

  test('le thème par défaut de l’API est servi quand rien n’est mémorisé', async ({ page }) => {
    await page.goto('/')

    await expect(page.locator('html')).toHaveAttribute('data-color-scheme', 'dark')
    expect(await primaryOf(page)).toBe('#da8d6d')
  })

  test('le choix mémorisé l’emporte, et le clair prend theme.light', async ({ page }) => {
    await remember(page, 'color-scheme', 'light')
    await page.goto('/')

    await expect(page.locator('html')).toHaveAttribute('data-color-scheme', 'light')
    expect(await primaryOf(page)).toBe('#c2410c')
  })

  test('avec un défaut sombre, « système » écrit auto et le garde au rechargement', async ({ page }) => {
    await page.goto('/')
    await expect(page.locator('html')).toHaveAttribute('data-color-scheme', 'dark')

    await page.getByTestId('color-scheme-toggle').click()
    await page.getByTestId('color-scheme-option-auto').click()
    expect(await page.evaluate(() => localStorage.getItem('color-scheme'))).toBe('auto')

    await page.reload()
    await expect(page.locator('html')).toHaveAttribute('data-color-scheme', 'auto')
  })

  test('le mode renforcé garde les couleurs de la bibliothèque', async ({ page, context }) => {
    await remember(page, 'a11y-mode', 'enhanced')
    await page.goto('/')
    await expect(page.locator('html')).toHaveAttribute('data-a11y-mode', 'enhanced')

    const withoutTheme = await context.newPage()
    await remember(withoutTheme, 'a11y-mode', 'enhanced')
    await withoutTheme.goto('/')
    await expect(withoutTheme.getByTestId('site-theme-style')).toHaveCount(0)

    expect(await primaryOf(page)).toBe(await primaryOf(withoutTheme))
  })

  for (const [mode, key, value] of [
    ['clair', 'color-scheme', 'light'],
    ['sombre', 'color-scheme', 'dark'],
    ['renforcé', 'a11y-mode', 'enhanced'],
  ] as const) {
    for (const route of ['/', '/catalogue', '/contact']) {
      test(`la page ${route} en mode ${mode} ne présente aucune violation axe`, async ({ page }) => {
        await remember(page, key, value)
        await page.goto(route)
        await expect(page.getByTestId('site-theme-style')).toHaveCount(1)

        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([])
      })
    }
  }
})

test.describe('Icône et image de partage du site', () => {
  test('sans icône ni image de partage, les fichiers du site', async ({ page }) => {
    await page.goto('/contact')

    await expect(page.locator('link[rel="icon"]')).toHaveCount(2)
    await expect(page.locator('link[rel="icon"]').first()).toHaveAttribute('href', '/favicon.ico')
    await expect(page.locator('link[rel="apple-touch-icon"]')).toHaveAttribute('href', '/apple-touch-icon.png')
    await expect(page.locator('meta[property="og:image"]')).toHaveAttribute('content', /\/og-image\.jpg$/)
  })
})

test.describe('Icône et image de partage du site saisies @identite', () => {
  test('les favicons, le manifeste et og:image pointent vers les fichiers de l’API', async ({ page, request }) => {
    await page.setExtraHTTPHeaders({ 'X-Scenario': 'identite-icone' })
    await page.goto('/contact')

    const icon = page.locator('link[rel="icon"]')
    await expect(icon).toHaveCount(1)
    await expect(icon).toHaveAttribute('href', 'http://localhost:3232/media/icone-oraki-32.png')
    await expect(icon).toHaveAttribute('sizes', '32x32')
    await expect(page.locator('link[rel="apple-touch-icon"]')).toHaveAttribute('href', 'http://localhost:3232/media/icone-oraki-180.png')
    await expect(page.locator('meta[property="og:image"]')).toHaveAttribute('content', 'http://localhost:3232/media/partage-oraki-1200.webp')
    await expect(page.locator('meta[name="twitter:image"]')).toHaveAttribute('content', 'http://localhost:3232/media/partage-oraki-1200.webp')

    const manifest = await (await request.get('/manifest.webmanifest', { headers: { 'X-Scenario': 'identite-icone' } })).json()
    expect(manifest.icons.map((item: { src: string }) => item.src)).toEqual([
      'http://localhost:3232/media/icone-oraki-192.png',
      'http://localhost:3232/media/icone-oraki-512.png',
    ])
  })
})
