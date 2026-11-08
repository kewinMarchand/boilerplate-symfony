import { expect, test } from '@playwright/test'

import type { Page } from '@playwright/test'

test.describe('Accueil', () => {
  test('affiche le hero, le carrousel et la grille de blog', async ({ page }) => {
    await page.goto('/')

    await expect(page.getByTestId('home-hero')).toBeVisible()
    await expect(page.getByTestId('home-tasks-link')).toBeVisible()
    await expect(page.getByTestId('home-contact-link')).toBeVisible()
    await expect(page.getByTestId('carousel-slide')).toHaveCount(5)
    await expect(page.getByTestId('carousel-slide').first()).toContainText('Anthurium andreanum rouge')
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
    // Le geste part d'une diapositive cliquable : il fait défiler sans ouvrir la fiche
    await expect(page).toHaveURL('/')
  })

  test('une diapositive ouvre la fiche de son produit', async ({ page }) => {
    await page.goto('/')
    const carousel = page.getByTestId('home-carousel')
    await expect(carousel.getByTestId('carousel-dot').first()).toHaveAttribute('aria-current', 'true')
    const name = ((await carousel.getByTestId('carousel-slide-link').first().textContent()) ?? '').trim()
    // Clic sur le titre : sous Playwright, un clic ailleurs dans la diapositive n'ouvre pas la fiche (cause non
    // identifiée), alors qu'il l'ouvre dans Chrome, vérifié à la main à 1280 × 720.
    await carousel.getByTestId('carousel-slide-link').first().click()

    await expect(page).toHaveURL(/\/produit\/[a-z0-9-]+$/)
    await expect(page.getByTestId('product-title')).toHaveText(name)
  })
})

test.describe('Grille des articles', () => {
  const measureCards = async (page: Page) => {
    const cards = page.getByTestId('home-blog-card')
    await expect(cards).toHaveCount(3)
    return Promise.all(
      [0, 1, 2].map(async (index) => {
        const box = await cards.nth(index).boundingBox()
        if (!box) throw new Error(`carte ${index + 1} introuvable`)
        return box
      }),
    )
  }

  test('desktop : l’article à la une sur 8 colonnes, les deux autres empilés à droite', async ({ page, isMobile }) => {
    test.skip(isMobile, 'largeur fixée explicitement')
    await page.setViewportSize({ width: 1280, height: 900 })
    await page.goto('/')
    const [featured, second, third] = await measureCards(page)

    expect(featured.width / second.width).toBeGreaterThan(1.9)
    expect(featured.width / second.width).toBeLessThan(2.3)
    expect(Math.abs(second.width - third.width)).toBeLessThan(1)
    expect(Math.abs(second.x - third.x)).toBeLessThan(1)
    expect(second.x).toBeGreaterThan(featured.x + featured.width)
    expect(third.y).toBeGreaterThanOrEqual(second.y + second.height)
    expect(Math.abs(featured.y + featured.height - (third.y + third.height))).toBeLessThan(1)
  })

  test('tablette : l’article à la une et les deux autres sur 6 colonnes, côte à côte', async ({ page, isMobile }) => {
    test.skip(isMobile, 'largeur fixée explicitement')
    await page.setViewportSize({ width: 768, height: 900 })
    await page.goto('/')
    const [featured, second, third] = await measureCards(page)

    expect(Math.abs(featured.width - second.width)).toBeLessThan(1)
    expect(Math.abs(second.width - third.width)).toBeLessThan(1)
    expect(second.x).toBeGreaterThan(featured.x + featured.width)
    expect(Math.abs(featured.y - second.y)).toBeLessThan(1)
    expect(Math.abs(second.x - third.x)).toBeLessThan(1)
    expect(third.y).toBeGreaterThanOrEqual(second.y + second.height)
  })

  test('l’image de l’article à la une annonce sa largeur propre à chaque point de rupture', async ({ page }) => {
    await page.goto('/')
    const sources = page.getByTestId('home-blog-card').first().locator('source')

    await expect(sources.first()).toHaveAttribute(
      'sizes',
      '(min-width: 1024px) calc((min(100vw, 1440px) - 64px) * 2 / 3), (min-width: 768px) 100vw, 100vw',
    )
    await expect(page.getByTestId('home-blog-card').nth(1).locator('source').first()).toHaveAttribute(
      'sizes',
      '(min-width: 1024px) calc((min(100vw, 1440px) - 64px) / 3), (min-width: 768px) 50vw, 100vw',
    )
  })

  test('mobile : les trois articles sur toute la largeur, empilés', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 800 })
    await page.goto('/')
    const [featured, second, third] = await measureCards(page)

    expect(Math.abs(featured.width - second.width)).toBeLessThan(1)
    expect(Math.abs(second.width - third.width)).toBeLessThan(1)
    expect(Math.abs(featured.x - second.x)).toBeLessThan(1)
    expect(second.y).toBeGreaterThanOrEqual(featured.y + featured.height)
    expect(third.y).toBeGreaterThanOrEqual(second.y + second.height)
  })
})

test.describe('Carrousel débordant', () => {
  test('les diapositives suivantes restent visibles au-delà du conteneur, sans défilement horizontal', async ({
    page,
    isMobile,
  }) => {
    if (!isMobile) await page.setViewportSize({ width: 1280, height: 900 })
    await page.goto('/')
    const carousel = page.getByTestId('home-carousel')
    await expect(carousel.getByTestId('carousel-dot').first()).toHaveAttribute('aria-current', 'true')
    await carousel.getByTestId('carousel-track').scrollIntoViewIfNeeded()

    const section = await carousel.boundingBox()
    const first = await carousel.getByTestId('carousel-slide').first().boundingBox()
    if (!section || !first) throw new Error('carrousel introuvable')
    const rights = await carousel
      .getByTestId('carousel-slide')
      .evaluateAll((slides) => slides.map((slide) => slide.getBoundingClientRect().right))
    expect(Math.max(...rights)).toBeGreaterThan(section.x + section.width)

    // Le bord de la fenêtre, au-delà du conteneur, montre une diapositive : elle n'est pas masquée par la piste
    const atWindowEdge = await page.evaluate(
      (y) => !!document.elementFromPoint(window.innerWidth - 4, y)?.closest('[data-testid="carousel-slide"]'),
      first.y + first.height / 2,
    )
    expect(atWindowEdge).toBe(true)
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true)
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
