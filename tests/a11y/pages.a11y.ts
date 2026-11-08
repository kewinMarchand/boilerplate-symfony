import AxeBuilder from '@axe-core/playwright'
import { expect, test } from '@playwright/test'

import { useA11yMode } from '../e2e/a11yMode'
import { A11Y_MODES, ROUTES } from '../e2e/routes'

import type { Page } from '@playwright/test'

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']

const analyze = (page: Page) => new AxeBuilder({ page }).withTags(TAGS).exclude('.sf-toolbar').analyze()

for (const mode of A11Y_MODES) {
  for (const route of [...ROUTES, '/catalogue?exposition=soleil&taille=M&attr-feuillage=persistant', '/route-inexistante', '/panier']) {
    test(`la page ${route} en mode ${mode} ne présente aucune violation axe WCAG 2.1 AA`, async ({ page }) => {
      await useA11yMode(page, mode)
      await page.goto(route)
      await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
      if (route === '/taches') await expect(page.getByTestId('tasks-list')).toBeVisible()
      if (route === '/panier') await expect(page.getByTestId('cart-empty')).toBeVisible()

      expect((await analyze(page)).violations).toEqual([])
    })
  }

  test(`le panier rempli en mode ${mode} ne présente aucune violation axe`, async ({ page }) => {
    await useA11yMode(page, mode)
    await page.goto('/produit/monstera-deliciosa')
    await page.getByTestId('add-to-cart').click()
    await expect(page.getByTestId('cart-count')).toHaveText('1')
    await page.goto('/panier')
    await expect(page.getByTestId('cart-line')).toHaveCount(1)

    expect((await analyze(page)).violations).toEqual([])
  })

  test(`le menu de navigation ouvert en mode ${mode} ne présente aucune violation axe`, async ({ page, isMobile }) => {
    await useA11yMode(page, mode)
    await page.goto('/')
    if (isMobile) {
      await page.getByTestId('layout-mobile-menu-toggle').click()
      await page.getByTestId('layout-mobile-menu').getByRole('button', { name: 'Catalogue' }).click()
    } else {
      await page.getByTestId('layout-category-menu-toggle').click()
      await page.getByTestId('layout-category-menu').getByRole('link', { name: "Plantes d’intérieur" }).hover()
    }

    expect((await analyze(page)).violations).toEqual([])
  })

  for (const route of ['/_error/500', '/charte-graphique']) {
    test(`la page ${route} en mode ${mode} ne présente aucune violation axe @dev`, async ({ page }) => {
      await useA11yMode(page, mode)
      await page.goto(route)
      await expect(page.getByRole('heading', { level: 1 })).toBeVisible()

      expect((await analyze(page)).violations).toEqual([])
    })
  }
}
