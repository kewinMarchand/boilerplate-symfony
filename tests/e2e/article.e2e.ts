import { expect, test } from '@playwright/test'

const ARTICLE = '/blog/un-carrousel-accessible-sans-dependance'

test.describe('Fiche article', () => {
  test('s’ouvre depuis une carte de l’accueil', async ({ page }) => {
    await page.goto('/')

    const card = page.getByTestId('home-blog-card').first()
    const title = (await card.locator('h3').textContent()) ?? ''
    await card.getByRole('link').click()

    await expect(page).toHaveURL(/\/blog\/[a-z0-9-]+$/)
    await expect(page.getByTestId('article-title')).toHaveText(title)
  })

  test('affiche le titre, la date, l’image de l’API et le texte rendu côté serveur', async ({
    page,
    request,
  }) => {
    const html = await (await request.get(ARTICLE)).text()
    expect(html).toContain('<h2>Partir du défilement natif</h2>')

    await page.goto(ARTICLE)
    await expect(page.getByTestId('article-title')).toHaveText(
      'Un carrousel accessible sans dépendance',
    )
    await expect(page.getByTestId('article-page').locator('time')).toHaveAttribute(
      'datetime',
      '2026-09-22',
    )
    await expect(page.getByTestId('article-body').locator('li')).toHaveCount(3)
    const image = page.getByTestId('article-page').locator('img').first()
    await expect(image).toHaveAttribute('fetchpriority', 'high')
    await expect
      .poll(() => image.evaluate((element: HTMLImageElement) => element.naturalWidth))
      .toBeGreaterThan(0)
    await expect(page.getByTestId('layout-breadcrumb')).toContainText(
      'Un carrousel accessible sans dépendance',
    )
  })

  test('expose ses données structurées d’article', async ({ page }) => {
    await page.goto(ARTICLE)

    expect(await page.locator('meta[property="og:type"]').getAttribute('content')).toBe('article')
    const types = (await page.locator('script[type="application/ld+json"]').allTextContents()).map(
      (text) => (JSON.parse(text) as { '@type': string })['@type'],
    )
    expect(types).toEqual(expect.arrayContaining(['BlogPosting', 'BreadcrumbList']))
  })

  test('répond 404 pour un article inconnu', async ({ page }) => {
    const response = await page.goto('/blog/article-inconnu')

    expect(response?.status()).toBe(404)
  })
})
