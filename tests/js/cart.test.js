import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { addItem, countItems, formatPrice, MAX_QUANTITY, removeItem, setQuantity, totalPrice } from '../../assets/cart.js'

const MONSTERA = { slug: 'monstera-deliciosa', name: 'Monstera deliciosa', price: 3490 }
const FERN = { slug: 'fougere-de-boston', name: 'Fougère de Boston', price: 1590 }

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
    const lines = setQuantity(addItem([], MONSTERA), MONSTERA.slug, 250)

    assert.equal(lines[0].quantity, MAX_QUANTITY)
    assert.equal(addItem(lines, MONSTERA)[0].quantity, MAX_QUANTITY)
    assert.equal(setQuantity(lines, MONSTERA.slug, 0)[0].quantity, 1)
  })

  it('retire une ligne', () => {
    assert.deepEqual(removeItem(addItem(addItem([], MONSTERA), FERN), MONSTERA.slug), [{ ...FERN, quantity: 1 }])
  })

  it('affiche les centimes en euros', () => {
    assert.match(formatPrice(10470), /^104,70\s€$/)
  })
})
