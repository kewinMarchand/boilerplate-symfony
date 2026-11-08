import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { parseCart } from '../../assets/cart_storage.js'

describe('parseCart', () => {
  it('lit un panier valide', () => {
    const lines = [{ slug: 'monstera-deliciosa', name: 'Monstera deliciosa', price: 3490, quantity: 2 }]

    assert.deepEqual(parseCart(JSON.stringify(lines)), lines)
  })

  it('rend un panier vide pour une entrée absente, illisible ou invalide', () => {
    assert.deepEqual(parseCart(null), [])
    assert.deepEqual(parseCart('{pas du json'), [])
    assert.deepEqual(parseCart(JSON.stringify({ slug: 'x' })), [])
    assert.deepEqual(parseCart(JSON.stringify([{ slug: 'x', name: 'X', price: -1, quantity: 1 }])), [])
    assert.deepEqual(parseCart(JSON.stringify([{ slug: 'x', name: 'X', price: 1, quantity: 100 }])), [])
    assert.deepEqual(parseCart(JSON.stringify([{ slug: 'x', name: 'X', price: 1.5, quantity: 1 }])), [])
  })
})
