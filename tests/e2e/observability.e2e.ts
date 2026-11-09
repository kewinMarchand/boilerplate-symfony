import { readFileSync } from 'node:fs'
import { createServer } from 'node:http'
import { gunzipSync } from 'node:zlib'
import { expect, test } from '@playwright/test'

import type { Page } from '@playwright/test'
import type { Server } from 'node:http'

// Faux GlitchTip local : le DSN factice de la phase @observabilite de make test-e2e pointe ici.
const GLITCHTIP_PORT = 4067

const envelopes: string[] = []
const events = () => envelopes.filter((body) => body.includes('"type":"event"'))

const startFakeGlitchtip = () =>
  createServer((request, response) => {
    response.setHeader('access-control-allow-origin', '*')
    response.setHeader('access-control-allow-headers', '*')
    if (request.method === 'OPTIONS') {
      response.writeHead(204).end()
      return
    }
    const chunks: Buffer[] = []
    request.on('data', (chunk: Buffer) => chunks.push(chunk))
    request.on('end', () => {
      const body = Buffer.concat(chunks)
      const gzipped = request.headers['content-encoding'] === 'gzip'
      envelopes.push((gzipped ? gunzipSync(body) : body).toString('utf8'))
      response.writeHead(200, { 'content-type': 'application/json' }).end('{}')
    })
  }).listen(GLITCHTIP_PORT)

const throwInBrowser = async (page: Page, message: string) => {
  await page.waitForFunction(() => typeof (window as { Sentry?: { getClient?: () => unknown } }).Sentry?.getClient?.() === 'object')
  await page.evaluate((text) => {
    setTimeout(() => {
      throw new Error(text)
    })
  }, message)
}

const MATOMO_TRACKER = readFileSync(new URL('./fixtures/matomo-tracker.js', import.meta.url), 'utf8')

const interceptMatomo = async (page: Page) => {
  const pageViews: string[] = []
  await page.route('http://matomo.test/matomo.js', (route) =>
    route.fulfill({ contentType: 'text/javascript', body: MATOMO_TRACKER }),
  )
  await page.route('http://matomo.test/matomo.php?*', (route) => {
    pageViews.push(route.request().url())
    return route.fulfill({ status: 204 })
  })
  return pageViews
}

test('sans variable, aucun traceur n’est chargé', async ({ page }) => {
  const requests: string[] = []
  page.on('request', (request) => requests.push(request.url()))
  await page.goto('/donnees-personnelles')
  await page.waitForLoadState('load')

  await expect(page.locator('body')).not.toHaveAttribute('data-controller', /.+/)
  await expect(page.getByTestId('legal-audience-measurement')).toHaveCount(0)
  expect(requests.filter((url) => url.includes('sentry-browser') || url.includes('matomo'))).toEqual([])
})

test.describe('Mesure d’audience Matomo, avec un traceur factice @observabilite', () => {
  test.skip(({ isMobile }) => isMobile, 'navigation par les liens du header desktop')

  test('une page vue par navigation, sans cookie', async ({ page, context }) => {
    const pageViews = await interceptMatomo(page)
    await page.goto('/')
    await expect.poll(() => pageViews.length).toBe(1)

    await page.getByRole('navigation', { name: 'Navigation principale' }).getByRole('link', { name: 'Contact' }).click()
    await expect(page).toHaveURL(/\/contact$/)
    await expect.poll(() => pageViews.length).toBe(2)
    expect(new URL(pageViews[1]).searchParams.get('url')).toMatch(/\/contact$/)

    const commands = await page.evaluate(() => (window as { __matomoCommands?: string[][] }).__matomoCommands ?? [])
    expect(commands[0]).toEqual(['disableCookies'])
    expect(await context.cookies()).toEqual([])
  })

  test('la page Données personnelles décrit la mesure d’audience', async ({ page }) => {
    await interceptMatomo(page)
    await page.goto('/donnees-personnelles')

    await expect(page.getByTestId('legal-audience-measurement')).toContainText('sans cookie')
  })

  test('un visiteur qui s’oppose à la mesure n’est plus mesuré, même après rechargement', async ({ page }) => {
    const pageViews = await interceptMatomo(page)
    await page.goto('/donnees-personnelles')
    await expect.poll(() => pageViews.length).toBe(1)

    await page.getByTestId('legal-opposition-mesure').check()
    await expect(page.getByTestId('legal-opposition-etat')).toHaveText(/ne sont plus mesurées/)

    // Le refus est lu avant la première commande : la file Matomo n'est jamais créée, donc aucun
    // traceur chargé ni aucune requête, sans dépendre d'un délai d'attente.
    await page.reload()
    await expect(page.getByTestId('legal-opposition-etat')).toHaveText(/ne sont plus mesurées/)
    expect(await page.evaluate(() => (window as unknown as { _paq?: unknown })._paq)).toBeUndefined()
    expect(pageViews).toHaveLength(1)

    await page.getByTestId('legal-opposition-mesure').uncheck()
    await expect(page.getByTestId('legal-opposition-etat')).toHaveText(/sont mesurées, de façon/)
  })
})

test.describe('Suivi des erreurs GlitchTip, avec un DSN factice @observabilite', () => {
  test.describe.configure({ mode: 'serial' })

  let glitchtip: Server | undefined

  test.beforeAll(() => {
    test.skip(test.info().project.name !== 'desktop', 'Vérification des envois, une seule fois.')
    glitchtip = startFakeGlitchtip()
  })

  test.afterAll(() => {
    glitchtip?.close()
  })

  test.beforeEach(() => {
    envelopes.length = 0
  })

  test('une erreur du navigateur est envoyée après l’affichage', async ({ page }) => {
    await page.goto('/')
    await throwInBrowser(page, 'Erreur navigateur volontaire')

    await expect
      .poll(() => events().some((body) => body.includes('Erreur navigateur volontaire')))
      .toBe(true)
  })

  test('rien n’est envoyé pour une 404', async ({ page }) => {
    expect((await page.goto('/route-inexistante'))?.status()).toBe(404)

    await throwInBrowser(page, 'Témoin après la 404')
    await expect.poll(() => events().length).toBeGreaterThan(0)

    expect(events()).toHaveLength(1)
    expect(events()[0]).toContain('Témoin après la 404')
  })
})
