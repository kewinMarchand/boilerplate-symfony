import { expect, test } from '@playwright/test'

test.describe('Navigation', () => {
  test('le lien actif porte aria-current', async ({ page, isMobile }) => {
    test.skip(isMobile, 'la navigation principale est dans le menu mobile')
    await page.goto('/contact')

    const nav = page.getByRole('navigation', { name: 'Navigation principale' })
    await expect(nav.getByRole('link', { name: 'Contact' })).toHaveAttribute('aria-current', 'page')
    await expect(nav.getByRole('link', { name: 'Accueil' })).not.toHaveAttribute('aria-current')
  })

  test('le logo mène à l’accueil et porte aria-current sur l’accueil', async ({ page }) => {
    await page.goto('/contact')
    const logo = page.getByTestId('layout-logo')
    await expect(logo).toHaveAccessibleName('Boilerplate')
    await expect(logo).not.toHaveAttribute('aria-current')

    await logo.click()
    await expect(page).toHaveURL(/\/$/)
    await expect(page.getByTestId('layout-logo')).toHaveAttribute('aria-current', 'page')
  })

  test('le lien d’évitement mène au contenu principal', async ({ page }) => {
    await page.goto('/')
    await page.keyboard.press('Tab')

    const skipLink = page.getByRole('link', { name: 'Aller au contenu principal' })
    await expect(skipLink).toBeFocused()
    await skipLink.press('Enter')
    await expect(page).toHaveURL(/#main$/)
  })

  test('le pied de page mène aux quatre pages légales', async ({ page }) => {
    await page.goto('/')

    const legal = page.getByRole('navigation', { name: 'Liens légaux' })
    await expect(legal.getByRole('link')).toHaveCount(4)
    await legal.getByRole('link', { name: 'Accessibilité : non conforme' }).click()
    await expect(page.getByRole('heading', { level: 1, name: "Déclaration d'accessibilité" })).toBeVisible()
  })

  test('les favicons et le manifeste sont déclarés', async ({ page, request }) => {
    await page.goto('/')

    for (const href of ['/favicon.ico', '/favicon.svg', '/apple-touch-icon.png', '/manifest.webmanifest']) {
      await expect(page.locator(`link[href="${href}"]`)).toHaveCount(1)
      expect((await request.get(href)).status()).toBe(200)
    }
  })
})
