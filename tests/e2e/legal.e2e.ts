import { expect, test } from '@playwright/test'

test.describe('Pages légales', () => {
  test('les mentions légales affichent le texte de l’API, section par section', async ({ page }) => {
    await page.goto('/mentions-legales')

    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Mentions légales')
    await expect(page.getByTestId('legal-section')).toHaveCount(4)
    await expect(page.getByTestId('legal-section').first()).toContainText('Exemple SAS')
    await expect(page.getByTestId('legal-error')).toHaveCount(0)
  })

  test('la déclaration est non conforme tant qu’aucun audit n’est renseigné', async ({ page }) => {
    await page.goto('/accessibilite')

    await expect(page.getByRole('heading', { level: 1 })).toHaveText("Déclaration d'accessibilité")
    const compliance = page.getByTestId('legal-section').first()
    await expect(compliance).toContainText('non conforme')
    await expect(compliance).toContainText("Aucun audit n'a encore été réalisé.")
  })
})

test.describe('Pages légales, API en erreur (X-Scenario: error)', () => {
  test.use({ extraHTTPHeaders: { 'X-Scenario': 'error' } })

  test('le titre reste et un message remplace le texte', async ({ page }) => {
    const response = await page.goto('/donnees-personnelles')

    expect(response?.status()).toBe(503)
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Données personnelles')
    await expect(page.getByTestId('legal-error')).toContainText('Impossible de charger cette page pour le moment.')
    await expect(page.getByTestId('legal-section')).toHaveCount(0)
  })
})
