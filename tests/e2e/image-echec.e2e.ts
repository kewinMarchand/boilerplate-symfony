import AxeBuilder from '@axe-core/playwright'
import { expect, test } from '@playwright/test'

import { useA11yMode } from './a11yMode'
import { A11Y_MODES } from './routes'

import type { Page } from '@playwright/test'

const firstCard = (page: Page) => page.getByTestId('catalog-product').first()

const imageStem = async (page: Page) => {
  const src = await firstCard(page).locator('img').getAttribute('src')
  return (src ?? '').replace(/-\d+\.\w+$/, '')
}

const failImage = async (page: Page, stem: string) => {
  await page.route(`${stem}-*`, (route) => route.fulfill({ status: 404, body: '' }))
}

test.describe('Image de l’API en échec', () => {
  test('une image de produit en 404 garde sa place, invisible, sur un fond de remplacement', async ({ page }) => {
    await page.goto('/catalogue')
    const card = firstCard(page)
    await expect(card.locator('img')).toHaveJSProperty('complete', true)
    const stem = await imageStem(page)
    const loadedBox = await card.boundingBox()

    await failImage(page, stem)
    await page.reload()

    const picture = card.locator('picture')
    await expect(picture).toHaveAttribute('data-image-error', '')
    await expect(picture.locator('img')).toHaveCSS('opacity', '0')
    await expect(picture.locator('img')).toHaveAccessibleName('Monstera deliciosa')
    expect(await card.boundingBox()).toEqual(loadedBox)
  })

  test('une image chargée ne porte pas la marque', async ({ page }) => {
    await page.goto('/catalogue')
    const img = firstCard(page).locator('img')
    await expect.poll(() => img.evaluate((element: HTMLImageElement) => element.naturalWidth)).toBeGreaterThan(0)

    await expect(firstCard(page).locator('picture')).not.toHaveAttribute('data-image-error')
    await expect(img).toHaveCSS('opacity', '1')
  })

  for (const mode of A11Y_MODES) {
    test(`une image en échec en mode ${mode} ne présente aucune violation axe WCAG 2.1 AA`, async ({ page }) => {
      await useA11yMode(page, mode)
      await page.goto('/catalogue')
      const stem = await imageStem(page)
      await failImage(page, stem)
      await page.reload()
      await expect(firstCard(page).locator('picture')).toHaveAttribute('data-image-error', '')

      const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze()
      expect(results.violations).toEqual([])
    })
  }
})
