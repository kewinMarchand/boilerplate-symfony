import { expect, test } from '@playwright/test'

test.describe('Blog', () => {
  test('liste les articles de l’API, du plus récent au plus ancien', async ({ page }) => {
    const response = await page.goto('/blog')

    expect(response?.status()).toBe(200)
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Blog')
    await expect(page.getByTestId('layout-breadcrumb').locator('[aria-current="page"]')).toHaveText('Blog')
    const cards = page.getByTestId('blog-list').getByTestId('blog-card')
    await expect(cards).toHaveCount(3)
    await expect(cards.first().locator('h2')).toHaveText('Le mode accessibilité renforcée')
    await expect(cards.first().locator('time')).toHaveAttribute('datetime', /^\d{4}-\d{2}-\d{2}$/)
    await expect(page.getByTestId('blog-empty')).toHaveCount(0)
    await expect(page.getByTestId('blog-error')).toHaveCount(0)
  })

  test('une carte ouvre la fiche de son article', async ({ page }) => {
    await page.goto('/blog')

    const card = page.getByTestId('blog-card').nth(1)
    const title = (await card.locator('h2').textContent()) ?? ''
    await card.getByRole('link').click()

    await expect(page).toHaveURL(/\/blog\/[a-z0-9-]+$/)
    await expect(page.getByTestId('article-title')).toHaveText(title)
  })

  test('expose ses données structurées de blog', async ({ page }) => {
    await page.goto('/blog')

    const data = (await page.locator('script[type="application/ld+json"]').allTextContents()).map(
      (text) => JSON.parse(text) as { '@type': string; blogPost?: unknown[] },
    )
    expect(data.map((item) => item['@type'])).toEqual(['Blog', 'BreadcrumbList'])
    expect(data[0].blogPost).toHaveLength(3)
  })

  test('l’accueil mène à la liste par « Tous les articles »', async ({ page }) => {
    await page.goto('/')
    await page.getByTestId('home-blog-all').click()

    await expect(page).toHaveURL(/\/blog$/)
    await expect(page.getByTestId('blog-card')).toHaveCount(3)
  })
})

test.describe('Blog, états de l’API', () => {
  test('liste vide : message amical', async ({ page }) => {
    await page.setExtraHTTPHeaders({ 'X-Scenario': 'empty' })
    await page.goto('/blog')

    await expect(page.getByTestId('blog-empty')).toBeVisible()
    await expect(page.getByTestId('blog-card')).toHaveCount(0)
  })

  test('erreur : message lisible et relance, en 503', async ({ page }) => {
    await page.setExtraHTTPHeaders({ 'X-Scenario': 'error' })
    const response = await page.goto('/blog')

    expect(response?.status()).toBe(503)
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Blog')
    await expect(page.getByTestId('blog-error')).toContainText('Impossible de charger les articles pour le moment.')
    await expect(page.getByTestId('blog-retry')).toHaveAttribute('href', '/blog')
  })

  test('chaque carte porte sa rubrique en sur-titre', async ({ page }) => {
    await page.goto('/blog')

    await expect(page.getByTestId('blog-card').getByTestId('article-section')).toHaveText(['Actualités', 'Conseils', 'Conseils'])
  })

  test('le filtre par rubrique met à jour l’URL et la liste', async ({ page }) => {
    await page.goto('/blog')

    const nav = page.getByRole('navigation', { name: 'Rubriques' })
    const links = nav.getByTestId('blog-section-link')
    await expect(links).toHaveText(['Toutes', 'Conseils', 'Actualités'])
    await expect(links.first()).toHaveAttribute('aria-current', 'page')
    await links.nth(1).click()

    await expect(page).toHaveURL(/\/blog\?rubrique=conseils$/)
    await expect(page.getByTestId('blog-card')).toHaveCount(2)
    await expect(page.getByTestId('blog-section-link').nth(1)).toHaveAttribute('aria-current', 'page')
    await expect(page.getByTestId('blog-section-link').first()).not.toHaveAttribute('aria-current')
  })

  test('sans rubrique, aucun filtre', async ({ page }) => {
    await page.setExtraHTTPHeaders({ 'X-Scenario': 'empty' })
    await page.goto('/blog')

    await expect(page.getByTestId('blog-empty')).toBeVisible()
    await expect(page.getByTestId('blog-sections')).toHaveCount(0)
  })

  test('la description saisie dans l’admin remplace celle de la page, le titre non saisi reste', async ({ page }) => {
    await page.goto('/blog')

    await expect(page).toHaveTitle('Blog · Boilerplate')
    await expect(page.locator('meta[name="description"]')).toHaveAttribute('content', /^Conseils et actualités du modèle/)
    await expect(page.locator('meta[property="og:description"]')).toHaveAttribute('content', /^Conseils et actualités du modèle/)
  })
})
