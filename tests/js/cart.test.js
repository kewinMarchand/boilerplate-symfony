import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { addItem, countItems, formatPrice, lineIn, MAX_QUANTITY, removeItem, setQuantity, totalPrice } from '../../assets/cart.js'

const MONSTERA = {
  id: '1',
  slug: 'monstera-deliciosa',
  name: 'Monstera deliciosa',
  price: 3490,
  translations: [
    { locale: 'fr', slug: 'monstera-deliciosa', name: 'Monstera deliciosa' },
    { locale: 'en', slug: 'swiss-cheese-plant', name: 'Swiss cheese plant' },
  ],
}
const FERN = { id: '2', slug: 'fougere-de-boston', name: 'Fougère de Boston', price: 1590, translations: [] }

describe('panier', () => {
  it('ajoute une ligne puis cumule les ajouts du même produit', () => {
    const lines = addItem(addItem(addItem([], MONSTERA), FERN), MONSTERA)

    assert.deepEqual(lines, [
      { ...MONSTERA, quantity: 2 },
      { ...FERN, quantity: 1 },
    ])
    assert.equal(countItems(lines), 3)
    assert.equal(totalPrice(lines), 2 * 3490 + 1590)
  })

  it('plafonne la quantité à 99 et la garde au moins à 1', () => {
    const lines = setQuantity(addItem([], MONSTERA), MONSTERA.id, 250)

    assert.equal(lines[0].quantity, MAX_QUANTITY)
    assert.equal(addItem(lines, MONSTERA)[0].quantity, MAX_QUANTITY)
    assert.equal(setQuantity(lines, MONSTERA.id, 0)[0].quantity, 1)
  })

  it('retire une ligne', () => {
    assert.deepEqual(removeItem(addItem(addItem([], MONSTERA), FERN), MONSTERA.id), [{ ...FERN, quantity: 1 }])
  })

  it('identifie une ligne par le produit, quelle que soit la langue de l’ajout', () => {
    const lines = addItem(addItem([], MONSTERA), { ...MONSTERA, slug: 'swiss-cheese-plant', name: 'Swiss cheese plant' })

    assert.equal(lines.length, 1)
    assert.equal(lines[0].quantity, 2)
  })

  it('prend le slug et le nom de la langue de la page, ceux de l’ajout à défaut', () => {
    assert.deepEqual(lineIn({ ...MONSTERA, quantity: 1 }, 'en'), { slug: 'swiss-cheese-plant', name: 'Swiss cheese plant' })
    assert.deepEqual(lineIn({ ...MONSTERA, quantity: 1 }, 'de'), { slug: 'monstera-deliciosa', name: 'Monstera deliciosa' })
    assert.deepEqual(lineIn({ ...FERN, quantity: 1 }, 'en'), { slug: 'fougere-de-boston', name: 'Fougère de Boston' })
  })

  it('affiche les centimes en euros', () => {
    assert.match(formatPrice(10470), /^104,70\s€$/)
  })

  it('suit la langue de la page', () => {
    assert.equal(formatPrice(3490, 'en'), '€34.90')
  })
})
