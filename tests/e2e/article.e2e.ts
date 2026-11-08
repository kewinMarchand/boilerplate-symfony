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
    await expect(page.getByTestId('article-page').locator('time').first()).toHaveAttribute(
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

  test('le fil d’Ariane mène à la liste des articles', async ({ page }) => {
    await page.goto(ARTICLE)

    const breadcrumb = page.getByTestId('layout-breadcrumb')
    await expect(breadcrumb.getByRole('listitem')).toHaveText(['Accueil', 'Blog', 'Un carrousel accessible sans dépendance'])
    await breadcrumb.getByRole('link', { name: 'Blog' }).click()
    await expect(page).toHaveURL(/\/blog$/)
    await expect(page.getByTestId('blog-list')).toBeVisible()
  })

  test('le lien « Tous les articles » mène à la liste', async ({ page }) => {
    await page.goto(ARTICLE)
    await page.getByTestId('article-page').getByRole('link', { name: 'Tous les articles' }).click()

    await expect(page).toHaveURL(/\/blog$/)
    await expect(page.getByTestId('blog-list')).toBeVisible()
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

  test('affiche la rubrique, le format et le lecteur audio avec sa durée et son intervenant', async ({ page }) => {
    await page.goto('/blog/un-carrousel-accessible-sans-dependance')

    await expect(page.getByTestId('article-page').getByTestId('article-section')).toHaveText('Rubrique : Conseils')
    await expect(page.getByTestId('article-format')).toHaveCount(0)
    const audio = page.getByTestId('article-audio')
    await expect(audio).toHaveAttribute('controls', '')
    await expect(audio).toHaveAttribute('preload', 'none')
    await expect(audio).not.toHaveAttribute('autoplay')
    expect(await audio.evaluate((element: HTMLAudioElement) => element.paused)).toBe(true)
    await expect(page.getByTestId('article-audio-duration')).toHaveText('4 s')
    await expect(page.getByTestId('article-audio-speaker')).toHaveText('Présenté par Hina Teriierooiterai')
  })

  test('affiche le format sans lecteur audio quand l’article n’en a pas', async ({ page }) => {
    await page.goto('/blog/pourquoi-rendre-le-contenu-cote-serveur')

    await expect(page.getByTestId('article-format')).toHaveText('Format : Guide')
    await expect(page.getByTestId('article-audio')).toHaveCount(0)
  })
})
