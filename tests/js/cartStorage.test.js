import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { parseCart } from '../../assets/cart_storage.js'

describe('parseCart', () => {
  it('lit un panier valide', () => {
    const lines = [
      {
        id: '1',
        slug: 'monstera-deliciosa',
        name: 'Monstera deliciosa',
        price: 3490,
        quantity: 2,
        translations: [{ locale: 'en', slug: 'swiss-cheese-plant', name: 'Swiss cheese plant' }],
      },
    ]

    assert.deepEqual(parseCart(JSON.stringify(lines)), lines)
  })

  it('relit un panier enregistré avant les langues : le slug identifie la ligne', () => {
    const lines = [{ slug: 'monstera-deliciosa', name: 'Monstera deliciosa', price: 3490, quantity: 2 }]

    assert.deepEqual(parseCart(JSON.stringify(lines)), [{ ...lines[0], id: 'monstera-deliciosa', translations: [] }])
  })

  it('rend un panier vide pour une entrée absente, illisible ou invalide', () => {
    assert.deepEqual(parseCart(null), [])
    assert.deepEqual(parseCart('{pas du json'), [])
    assert.deepEqual(parseCart(JSON.stringify({ slug: 'x' })), [])
    assert.deepEqual(parseCart(JSON.stringify([{ slug: 'x', name: 'X', price: -1, quantity: 1 }])), [])
    assert.deepEqual(parseCart(JSON.stringify([{ slug: 'x', name: 'X', price: 1, quantity: 100 }])), [])
    assert.deepEqual(parseCart(JSON.stringify([{ slug: 'x', name: 'X', price: 1.5, quantity: 1 }])), [])
    assert.deepEqual(parseCart(JSON.stringify([{ id: '', slug: 'x', name: 'X', price: 1, quantity: 1 }])), [])
    assert.deepEqual(parseCart(JSON.stringify([{ slug: 'x', name: 'X', price: 1, quantity: 1, translations: [{ locale: 'en' }] }])), [])
  })
})
