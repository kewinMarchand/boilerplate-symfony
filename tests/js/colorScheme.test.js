import assert from 'node:assert/strict'
import { beforeEach, describe, it } from 'node:test'

import { applyColorScheme, colorSchemeLabel, currentColorScheme } from '../../assets/color_scheme.js'

const createStorage = () => {
  const values = new Map()
  return {
    getItem: (key) => values.get(key) ?? null,
    setItem: (key, value) => values.set(key, value),
    removeItem: (key) => values.delete(key),
  }
}

describe('applyColorScheme', () => {
  let root

  beforeEach(() => {
    root = { dataset: { colorScheme: 'auto' } }
    globalThis.localStorage = createStorage()
  })

  it('pose le thème choisi et le mémorise', () => {
    applyColorScheme(root, 'dark')

    assert.equal(root.dataset.colorScheme, 'dark')
    assert.equal(currentColorScheme(root), 'dark')
    assert.equal(globalThis.localStorage.getItem('color-scheme'), 'dark')
  })

  it('« système » remet auto et le mémorise, pour l’emporter sur le défaut du site', () => {
    applyColorScheme(root, 'light')
    applyColorScheme(root, 'auto')

    assert.equal(root.dataset.colorScheme, 'auto')
    assert.equal(globalThis.localStorage.getItem('color-scheme'), 'auto')
  })

  it('reste utilisable quand le stockage est indisponible', () => {
    globalThis.localStorage = {
      setItem: () => {
        throw new Error('SecurityError')
      },
    }

    assert.equal(applyColorScheme(root, 'dark'), 'dark')
    assert.equal(root.dataset.colorScheme, 'dark')
  })

  it('lit auto pour une valeur inconnue', () => {
    root.dataset.colorScheme = 'violet'

    assert.equal(currentColorScheme(root), 'auto')
  })
})

describe('colorSchemeLabel', () => {
  const labels = {
    auto: 'Thème : système',
    light: 'Thème : clair',
    dark: 'Thème : sombre',
    suspended: ", suspendu par l'accessibilité renforcée",
  }

  it('nomme le thème courant', () => {
    assert.equal(colorSchemeLabel('auto', false, labels), 'Thème : système')
    assert.equal(colorSchemeLabel('dark', false, labels), 'Thème : sombre')
  })

  it('signale un choix suspendu par le mode renforcé, sauf le clair', () => {
    assert.equal(colorSchemeLabel('dark', true, labels), "Thème : sombre, suspendu par l'accessibilité renforcée")
    assert.equal(colorSchemeLabel('light', true, labels), 'Thème : clair')
  })
})
