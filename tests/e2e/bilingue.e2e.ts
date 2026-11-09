import AxeBuilder from '@axe-core/playwright'
import { expect, test } from '@playwright/test'

import { useA11yMode } from './a11yMode'
import { A11Y_MODES } from './routes'
import { messages } from './translations'

import type { Page } from '@playwright/test'

/**
 * Langues du site (section 29 bis de la spec commune). Le site monolingue tourne avec la suite principale. Le site bilingue
 * (@bilingue) tourne dans sa propre phase de make test-e2e : l'image de production servie avec API_SCENARIO=bilingual,
 * qui fixe `X-Scenario: bilingual` sur tous les appels à l'API. L'API active alors `en` après `fr`, avec ces traductions
 * (produit, article, catégories, accroche) ; tout le reste retombe sur le français, champ par champ.
 */
const PRODUCT = { fr: '/produit/monstera-deliciosa', en: '/en/produit/swiss-cheese-plant' }
const ARTICLE = { fr: '/blog/le-mode-accessibilite-renforcee', en: '/en/blog/enhanced-accessibility-mode' }
const CATEGORY = { fr: '/catalogue/plantes-interieur/feuillages', en: '/en/catalogue/indoor-plants/foliage-plants' }
const TAGLINE_EN = 'Website template with boilerplates-ui, equipped for QA and end-to-end testing.'

const EN_ROUTES = [
  '/en',
  '/en/catalogue',
  CATEGORY.en,
  PRODUCT.en,
  '/en/blog',
  ARTICLE.en,
  '/en/contact',
  '/en/taches',
  '/en/mentions-legales',
  '/en/plan-du-site',
  '/en/panier',
  '/en/route-inexistante',
]

const en = messages('en')
const fr = messages('fr')

/** Libellés par défaut de boilerplates-ui (tableau « Libellés d'interface » de son README). */
const LIBRARY_DEFAULTS = [
  '(obligatoire)',
  '(chargement en cours)',
  '(nouvelle fenêtre)',
  'Retirer le filtre :',
  'Information :',
  'Succès :',
  'Attention :',
  'Erreur :',
  'Fermer la notification',
  'Fermer',
  'Fil d’Ariane',
  "Fil d'Ariane",
  'Précédent',
  'Suivant',
  'Chargement en cours',
]

/**
 * Sections des fichiers de traduction lues par l'alerte, les champs, le chargement, les filtres, la pagination, le fil d'Ariane,
 * la 404 et le panier. Pas `carousel` : « carrousel » est aussi dans un titre d'article que l'API sert en français.
 */
const SECTIONS = ['ui', 'layout', 'nav', 'error', 'maintenance', 'catalog', 'cart', 'contact', 'tasks', 'common']

/**
 * Libellés d'interface français de ces sections, coupés aux variables (`%name%`) et aux pluriels, plus les défauts de la
 * bibliothèque, sauf ceux qu'un libellé anglais contient aussi (« Blog », « Contact ») ; fragments de moins de 4 caractères
 * ignorés.
 */
const FRENCH_LABELS = (() => {
  const english = Object.values(en).join('\n')
  const french = Object.entries(fr)
    .filter(([key]) => SECTIONS.includes(key.split('.')[0]))
    .flatMap(([, value]) => value.split(/%\w+%|\|/))
  return [...new Set([...french, ...LIBRARY_DEFAULTS].map((label) => label.trim()))].filter(
    (label) => label.length >= 4 && !english.includes(label),
  )
})()

/** Texte lu de la page : contenu (masqué compris, hors scripts et styles) et attributs annoncés. */
const readableText = (page: Page) =>
  page.evaluate(() => {
    const root = document.documentElement.cloneNode(true) as HTMLElement
    root.querySelectorAll('script, style').forEach((element) => element.remove())
    const attributes = ['aria-label', 'aria-roledescription', 'title', 'alt', 'placeholder']
    const announced = [...root.querySelectorAll('*')].flatMap((element) => attributes.map((name) => element.getAttribute(name) ?? ''))
    return [root.textContent ?? '', ...announced].join('\n')
  })

/** Libellé trouvé en mots entiers : « Grande » (taille) ne doit pas trouver « Grandes feuilles » (contenu de l'API non traduit). */
const containsWords = (text: string, label: string) => {
  const isLetter = (char: string | undefined) => char !== undefined && /\p{L}/u.test(char)
  for (let index = text.indexOf(label); index !== -1; index = text.indexOf(label, index + 1)) {
    if (!isLetter(text[index - 1]) && !isLetter(text[index + label.length])) return true
  }
  return false
}

const expectNoFrenchLabel = (text: string) => expect(FRENCH_LABELS.filter((label) => containsWords(text, label))).toEqual([])

const pathOf = (url: string | null) => (url === null ? null : new URL(url).pathname)

const hreflangs = (page: Page) =>
  page
    .locator('link[rel="alternate"][hreflang]')
    .evaluateAll((links) => Object.fromEntries(links.map((link) => [link.getAttribute('hreflang'), new URL(link.getAttribute('href') ?? '').pathname])))

test.describe('Site monolingue', () => {
  test('aucun sélecteur de langue, ni hreflang, ni og:locale:alternate', async ({ page }) => {
    await page.goto(PRODUCT.fr)

    await expect(page.getByTestId('product-title')).toBeVisible()
    await expect(page.locator('html')).toHaveAttribute('lang', 'fr')
    await expect(page.getByTestId('layout-languages')).toHaveCount(0)
    await expect(page.locator('link[rel="alternate"][hreflang]')).toHaveCount(0)
    await expect(page.locator('meta[property="og:locale:alternate"]')).toHaveCount(0)
  })

  test('un préfixe de langue non activée répond 404', async ({ page }) => {
    const response = await page.goto(PRODUCT.en)

    expect(response?.status()).toBe(404)
    await expect(page.locator('html')).toHaveAttribute('lang', 'fr')
  })
})

test.describe('Site bilingue fr, en, sans JavaScript @bilingue', () => {
  test.use({ javaScriptEnabled: false })

  test('le chargement des tâches est annoncé en anglais', async ({ page }) => {
    await page.goto('/en/taches')

    await expect(page.getByTestId('tasks-loading')).toHaveAttribute('aria-label', en['tasks.loading'])
    expectNoFrenchLabel(await readableText(page))
  })
})

test.describe('Site bilingue fr, en @bilingue', () => {
  test('l’accueil anglais est préfixé, en anglais, et ses liens internes gardent le préfixe', async ({ page }) => {
    await page.goto('/en')

    await expect(page.locator('html')).toHaveAttribute('lang', 'en')
    await expect(page.getByTestId('home-hero')).toContainText(TAGLINE_EN)
    await expect(page.getByTestId('home-tasks-link')).toHaveText(en['home.tasks_link'])
    await expect(page.getByTestId('home-tasks-link')).toHaveAttribute('href', '/en/taches')
    await expect(page.getByTestId('home-blog-all')).toHaveText(en['blog.all'])
    await expect(page.getByTestId('home-blog-all')).toHaveAttribute('href', '/en/blog')
    await expect(page.getByTestId('layout-logo')).toHaveAttribute('href', '/en')
  })

  test('la fiche produit anglaise sert le contenu, les libellés et le fil d’Ariane en anglais', async ({ page }) => {
    await page.goto(PRODUCT.en)

    await expect(page.locator('html')).toHaveAttribute('lang', 'en')
    await expect(page.getByTestId('product-title')).toHaveText('Swiss cheese plant')
    await expect(page.getByTestId('product-page')).toContainText(en['product.availability'])
    await expect(page.getByTestId('product-price')).toHaveText('€34.90')
    await expect(page.getByTestId('add-to-cart')).toHaveText(en['cart.add'])
    const breadcrumb = page.getByTestId('layout-breadcrumb')
    await expect(breadcrumb.getByRole('link').first()).toHaveText(en['nav.home'])
    await expect(breadcrumb.getByRole('link').first()).toHaveAttribute('href', '/en')
    await expect(breadcrumb.getByRole('link').nth(1)).toHaveAttribute('href', '/en/catalogue')
    await expect(breadcrumb).toContainText('Indoor plants')
    await expect(page).toHaveTitle('Swiss cheese plant (Monstera deliciosa)')
  })

  test('le sélecteur mène à l’équivalent de la fiche dans l’autre langue', async ({ page }) => {
    await page.goto(PRODUCT.en)
    const french = page.getByTestId('layout-language-fr')

    await expect(page.getByTestId('layout-languages')).toHaveAttribute('aria-label', en['layout.languages'])
    await expect(page.getByTestId('layout-language-en')).toHaveAttribute('aria-current', 'true')
    await expect(french).toHaveText('Français')
    await expect(french).toHaveAttribute('lang', 'fr')
    await expect(french).toHaveAttribute('hreflang', 'fr')
    await expect(french).toHaveAttribute('href', PRODUCT.fr)
    await expect(french).not.toHaveAttribute('aria-current')

    await french.click()
    await expect(page).toHaveURL(PRODUCT.fr)
    await expect(page.locator('html')).toHaveAttribute('lang', 'fr')
    await expect(page.getByTestId('product-title')).toHaveText('Monstera deliciosa')
    await expect(page.getByTestId('layout-language-fr')).toHaveAttribute('aria-current', 'true')
    await expect(page.getByTestId('layout-language-en')).toHaveAttribute('href', PRODUCT.en)
    await expect(page.getByTestId('layout-language-en')).toHaveText('English')
  })

  test('le sélecteur garde la catégorie et l’article dans l’autre langue', async ({ page }) => {
    await page.goto(CATEGORY.fr)
    await expect(page.getByTestId('layout-language-en')).toHaveAttribute('href', CATEGORY.en)

    await page.goto(CATEGORY.en)
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Foliage plants')
    await expect(page.getByTestId('layout-language-fr')).toHaveAttribute('href', CATEGORY.fr)
    await expect(page.getByTestId('catalog-product-link').first()).toHaveAttribute('href', /^\/en\/produit\//)

    await page.goto(ARTICLE.en)
    await expect(page.getByTestId('article-title')).toHaveText('The enhanced accessibility mode')
    await expect(page.getByTestId('layout-language-fr')).toHaveAttribute('href', ARTICLE.fr)
  })

  test('sans équivalent (page introuvable), le sélecteur mène à l’accueil de chaque langue', async ({ page }) => {
    const response = await page.goto('/en/route-inexistante')

    expect(response?.status()).toBe(404)
    await expect(page.locator('html')).toHaveAttribute('lang', 'en')
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(en['error.not_found'])
    await expect(page.getByTestId('layout-language-fr')).toHaveAttribute('href', '/')
    await expect(page.getByTestId('layout-language-en')).toHaveAttribute('href', '/en')
  })

  test('la navigation et les filtres entre pages anglaises gardent le préfixe', async ({ page, isMobile }) => {
    await page.goto('/en')
    await page.getByTestId('home-tasks-link').click()

    await expect(page).toHaveURL('/en/taches')
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(en['nav.tasks'])
    await expect(page.getByTestId('tasks-list')).toBeVisible()

    await page.goto('/en/catalogue')
    if (isMobile) await page.getByTestId('catalog-filters-open').click()
    await page.getByTestId('catalog-filter-exposure-ombre').check()
    await expect(page).toHaveURL('/en/catalogue?exposition=ombre')
    await expect(page.getByTestId('catalog-active-filter')).toHaveText(`${en['ui.remove_filter']} ${en['catalog.exposure.ombre']}`)
  })

  test('hreflang, x-default, canonique et og:locale suivent la langue de la page', async ({ page }) => {
    await page.goto(PRODUCT.en)

    expect(await hreflangs(page)).toEqual({ fr: PRODUCT.fr, en: PRODUCT.en, 'x-default': PRODUCT.fr })
    expect(pathOf(await page.locator('link[rel="canonical"]').getAttribute('href'))).toBe(PRODUCT.en)
    await expect(page.locator('meta[property="og:locale"]')).toHaveAttribute('content', 'en_US')
    await expect(page.locator('meta[property="og:locale:alternate"]')).toHaveAttribute('content', 'fr_FR')

    await page.goto('/contact')
    expect(await hreflangs(page)).toEqual({ fr: '/contact', en: '/en/contact', 'x-default': '/contact' })
    await expect(page.locator('html')).toHaveAttribute('lang', 'fr')
  })

  test('les données structurées déclarent la langue de la page', async ({ page }) => {
    await page.goto(ARTICLE.en)
    const posting = (await page.locator('script[type="application/ld+json"]').allTextContents())
      .map((text) => JSON.parse(text) as { '@type': string; inLanguage?: string; url?: string })
      .find((item) => item['@type'] === 'BlogPosting')

    expect(posting?.inLanguage).toBe('en-US')
    expect(pathOf(posting?.url ?? null)).toBe(ARTICLE.en)
  })

  test('le sitemap liste chaque adresse dans chaque langue, avec ses équivalents', async ({ request }) => {
    const sitemap = await (await request.get('/sitemap.xml')).text()

    expect(sitemap).toContain('xmlns:xhtml="http://www.w3.org/1999/xhtml"')
    for (const path of [PRODUCT.en, PRODUCT.fr, CATEGORY.en, ARTICLE.en, '/en/contact']) {
      expect(sitemap).toMatch(new RegExp(`<loc>https?://[^<]+${path}</loc>`))
    }
    expect(sitemap).toMatch(new RegExp(`hreflang="en" href="https?://[^"]+${PRODUCT.en}"`))
    expect(sitemap).toMatch(new RegExp(`hreflang="x-default" href="https?://[^"]+${PRODUCT.fr}"`))
  })

  test('le contact anglais valide et confirme en anglais', async ({ page }) => {
    await page.goto('/en/contact')
    await page.getByTestId('contact-name').fill('Ada')
    await page.getByTestId('contact-email').fill('ada')
    await page.getByTestId('contact-message').fill('Does this plant like low light?')
    await page.getByTestId('contact-submit').click()
    await expect(page.getByTestId('contact-email')).toHaveAccessibleDescription(/Enter a valid email address/)

    await page.getByTestId('contact-email').fill('ada@example.com')
    await page.getByTestId('contact-submit').click()

    await expect(page.getByTestId('contact-success')).toHaveText(`${en['ui.alert.success']}${en['contact.sent']}`)
  })

  test('le panier anglais annonce l’ajout, compte et lie la fiche en anglais', async ({ page }) => {
    await page.goto(PRODUCT.en)
    await page.getByTestId('add-to-cart').click()

    await expect(page.getByTestId('add-to-cart-status')).toContainText('Swiss cheese plant added to the cart.')
    await expect(page.getByTestId('add-to-cart-status').getByRole('link', { name: en['cart.view'] })).toHaveAttribute('href', '/en/panier')
    await expect(page.getByTestId('cart-link')).toHaveAccessibleName(/1 item$/)

    await page.goto('/en/panier')
    await expect(page.getByTestId('cart-line').getByRole('link')).toHaveAttribute('href', PRODUCT.en)
    await expect(page.getByTestId('cart-total')).toHaveText('€34.90')
    await expect(page.getByTestId('cart-remove')).toHaveAccessibleName('Remove Swiss cheese plant')
  })

  test('une ligne du panier prend le nom et le lien de la langue de la page', async ({ page }) => {
    await page.goto(PRODUCT.en)
    await page.getByTestId('add-to-cart').click()
    await expect(page.getByTestId('cart-count')).toHaveText('1')

    await page.goto('/panier')
    await expect(page.getByTestId('cart-line')).toContainText('Monstera deliciosa')
    await expect(page.getByTestId('cart-line').getByRole('link')).toHaveAttribute('href', PRODUCT.fr)

    await page.goto('/en/panier')
    await expect(page.getByTestId('cart-line')).toContainText('Swiss cheese plant')
    await expect(page.getByTestId('cart-line').getByRole('link')).toHaveAttribute('href', PRODUCT.en)
  })

  test('aucun libellé français sur les pages anglaises', async ({ page }) => {
    for (const path of [...EN_ROUTES, '/en/catalogue?exposition=soleil&page=2']) {
      await page.goto(path)
      await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
      if (path === '/en/taches') await expect(page.getByTestId('tasks-list')).toBeVisible()
      expectNoFrenchLabel(await readableText(page))
    }
  })

  test('filtres actifs, pagination et catalogue vide sans libellé français', async ({ page }) => {
    await page.goto('/en/catalogue?exposition=soleil&page=2')
    await expect(page.getByTestId('catalog-active-filter').first()).toContainText(en['ui.remove_filter'])
    await expect(page.getByTestId('catalog-pagination')).toHaveAttribute('aria-label', en['catalog.pagination'])
    expectNoFrenchLabel(await readableText(page))

    await page.goto('/en/catalogue?prix_max=1')
    await expect(page.getByTestId('catalog-empty')).toContainText(en['ui.alert.info'])
    expectNoFrenchLabel(await readableText(page))
  })

  test('champs obligatoires et alerte de confirmation du contact en anglais', async ({ page }) => {
    await page.goto('/en/contact')
    await expect(page.getByTestId('contact-name')).toHaveAccessibleName(`${en['contact.form.name']} ${en['ui.required']}`)
    expectNoFrenchLabel(await readableText(page))

    await page.getByTestId('contact-name').fill('Jane Doe')
    await page.getByTestId('contact-email').fill('jane@example.com')
    await page.getByTestId('contact-message').fill('A message long enough to be sent.')
    await page.getByTestId('contact-submit').click()
    await expect(page.getByTestId('contact-success')).toContainText(en['ui.alert.success'])
    expectNoFrenchLabel(await readableText(page))
  })

  test('la page introuvable suit la langue de l’adresse', async ({ page }) => {
    await page.goto('/en/route-inexistante')
    await expect(page.getByTestId('error-home-link')).toHaveAttribute('href', '/en')
    expectNoFrenchLabel(await readableText(page))

    await page.goto('/route-inexistante')
    await expect(page.locator('html')).toHaveAttribute('lang', 'fr')
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(fr['error.not_found'])
  })

  test('le panier anglais, vide puis rempli, est en anglais', async ({ page }) => {
    await page.goto('/en/panier')
    await expect(page.getByTestId('cart-empty')).toContainText(en['cart.empty'].trim())
    expectNoFrenchLabel(await readableText(page))

    await page.goto(PRODUCT.en)
    await page.getByTestId('add-to-cart').click()
    await expect(page.getByTestId('add-to-cart-status')).toContainText('Swiss cheese plant')
    expectNoFrenchLabel(await readableText(page))

    await page.goto('/en/panier')
    await expect(page.getByTestId('cart-line')).toBeVisible()
    expectNoFrenchLabel(await readableText(page))
  })

  for (const path of ['/de/contact', '/fr/contact', '/en/produit/monstera-deliciosa', '/en/sitemap.xml']) {
    test(`${path} répond 404`, async ({ page }) => {
      const response = await page.goto(path)

      expect(response?.status()).toBe(404)
    })
  }

  for (const mode of A11Y_MODES) {
    for (const path of EN_ROUTES) {
      test(`${path} en mode ${mode} ne présente aucune violation axe WCAG 2.1 AA`, async ({ page }) => {
        await useA11yMode(page, mode)
        await page.goto(path)
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
        if (path === '/en/taches') await expect(page.getByTestId('tasks-list')).toBeVisible()

        const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze()
        expect(results.violations).toEqual([])
      })
    }
  }
})
