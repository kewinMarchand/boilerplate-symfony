import { expect, test } from '@playwright/test'

test.describe('Accueil', () => {
  test('affiche le hero, le carrousel et la grille de blog', async ({ page }) => {
    await page.goto('/')

    await expect(page.getByTestId('home-hero')).toBeVisible()
    await expect(page.getByTestId('home-tasks-link')).toBeVisible()
    await expect(page.getByTestId('home-contact-link')).toBeVisible()
    await expect(page.getByTestId('carousel-slide')).toHaveCount(5)
    await expect(page.getByTestId('carousel-slide').first()).toContainText('Heliconia')
    await expect(page.getByTestId('home-carousel-error')).toHaveCount(0)
    await expect(page.getByTestId('home-blog-card')).toHaveCount(3)
    await expect(page.getByTestId('home-blog-empty')).toHaveCount(0)
    await expect(page.getByTestId('home-blog-error')).toHaveCount(0)
  })

  test('l’image du hero est prioritaire et jamais différée', async ({ page }) => {
    await page.goto('/')

    const image = page.getByTestId('home-hero').locator('img')
    await expect(image).toHaveAttribute('fetchpriority', 'high')
    await expect(image).not.toHaveAttribute('loading', 'lazy')
  })
  test('affiche les illustrations des articles servies par l’API', async ({ page }) => {
    await page.goto('/')
    const image = page.getByTestId('home-blog-card').first().locator('img')
    await image.scrollIntoViewIfNeeded()
    // L'origine de l'API vient de API_URL du conteneur PHP, que le conteneur Playwright ne reçoit pas
    await expect(image).toHaveAttribute('src', /^https?:\/\/[^/]+\/media\//)
    await expect
      .poll(() => image.evaluate((element: HTMLImageElement) => element.naturalWidth))
      .toBeGreaterThan(0)
  })
})

test.describe('Carrousel', () => {
  test('le bouton suivant fait avancer et précédent est désactivé au début', async ({ page }) => {
    await page.goto('/')
    const carousel = page.getByTestId('home-carousel')
    const dots = carousel.getByTestId('carousel-dot')

    await expect(dots.first()).toHaveAttribute('aria-current', 'true')
    await expect(carousel.getByTestId('carousel-prev')).toBeDisabled()

    await carousel.getByTestId('carousel-next').click()

    await expect(dots.nth(1)).toHaveAttribute('aria-current', 'true')
    await expect(carousel.getByTestId('carousel-prev')).toBeEnabled()
  })

  test('glisser à la souris fait avancer', async ({ page, isMobile }) => {
    test.skip(isMobile, 'la souris n’existe que sur le projet desktop')
    await page.goto('/')
    const carousel = page.getByTestId('home-carousel')
    await expect(carousel.getByTestId('carousel-dot').first()).toHaveAttribute('aria-current', 'true')

    const track = carousel.getByTestId('carousel-track')
    await track.scrollIntoViewIfNeeded()
    const box = await track.boundingBox()
    if (!box) throw new Error('piste du carrousel introuvable')
    const y = box.y + box.height / 2
    await page.mouse.move(box.x + box.width * 0.8, y)
    await page.mouse.down()
    await page.mouse.move(box.x + box.width * 0.2, y, { steps: 10 })
    await page.mouse.up()

    await expect(carousel.getByTestId('carousel-dot').first()).not.toHaveAttribute('aria-current')
    await expect(carousel.getByTestId('carousel-prev')).toBeEnabled()
  })
})

test.describe('Carrousel sans JavaScript', () => {
  test.use({ javaScriptEnabled: false })

  test('la piste défile nativement', async ({ page }) => {
    await page.goto('/')
    const track = page.getByTestId('carousel-track')

    await expect(page.getByTestId('carousel-dot')).toHaveCount(0)
    await expect(page.getByTestId('carousel-prev')).toBeHidden()
    await expect(page.getByTestId('carousel-next')).toBeHidden()
    const scrollable = await track.evaluate((element) => element.scrollWidth > element.clientWidth)
    expect(scrollable).toBe(true)

    await track.evaluate((element) => element.scrollBy({ left: element.clientWidth, behavior: 'instant' }))
    await expect.poll(() => track.evaluate((element) => element.scrollLeft)).toBeGreaterThan(0)
  })
})

test.describe('Articles de l’accueil, états de l’API', () => {
  test('liste vide : message amical', async ({ page }) => {
    await page.setExtraHTTPHeaders({ 'X-Scenario': 'empty' })
    await page.goto('/')

    await expect(page.getByTestId('home-blog-empty')).toBeVisible()
    await expect(page.getByTestId('home-blog-card')).toHaveCount(0)
  })

  test('erreur : message lisible', async ({ page }) => {
    await page.setExtraHTTPHeaders({ 'X-Scenario': 'error' })
    await page.goto('/')

    await expect(page.getByTestId('home-blog-error')).toBeVisible()
  })
})

test.describe('Carrousel de l’accueil, états de l’API', () => {
  test('aucune diapositive : la section disparaît', async ({ page }) => {
    await page.setExtraHTTPHeaders({ 'X-Scenario': 'empty' })
    await page.goto('/')

    await expect(page.getByTestId('home-hero')).toBeVisible()
    await expect(page.getByTestId('home-carousel')).toHaveCount(0)
    await expect(page.getByTestId('home-carousel-error')).toHaveCount(0)
  })

  test('erreur : message lisible à la place du carrousel', async ({ page }) => {
    await page.setExtraHTTPHeaders({ 'X-Scenario': 'error' })
    await page.goto('/')

    await expect(page.getByTestId('home-carousel-error')).toContainText('Impossible de charger le carrousel pour le moment.')
    await expect(page.getByTestId('home-carousel')).toHaveCount(0)
  })
})
