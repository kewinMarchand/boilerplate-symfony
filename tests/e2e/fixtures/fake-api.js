// Fausse API de la phase @identite de make test-e2e (port 3232) : sert l'identité du site avec logo, avec couleurs, avec
// icône et image de partage ou à vente en ligne coupée, et les fiches avec métadonnées saisies, selon X-Scenario (fixtures
// de tests/Fixtures/api/), que l'API locale n'a pas par défaut, et relaie tout le reste à l'API réelle.
import { readFileSync } from 'node:fs'
import { createServer, request as forward } from 'node:http'

const PORT = 3232
const API = new URL(process.env.API_TARGET ?? 'http://localhost:8090')
const FIXTURES = {
  'identite-logo': 'site-identity-logo',
  'identite-couleurs': 'site-identity-couleurs',
  'identite-icone': 'site-identity-icone',
  'sans-vente': 'site-identity-sans-vente',
}
const WITH_METAS = ['/api/products/monstera-deliciosa', '/api/articles/un-carrousel-accessible-sans-dependance']
const METAS = JSON.parse(readFileSync(new URL('../../Fixtures/api/metas.json', import.meta.url), 'utf8'))
const LOGO = readFileSync(new URL('../../../public/icon-512.png', import.meta.url))

createServer((incoming, outgoing) => {
  const fixture = FIXTURES[incoming.headers['x-scenario']]
  if (incoming.url === '/api/site-identity' && fixture) {
    outgoing.writeHead(200, { 'content-type': 'application/json' })
    outgoing.end(readFileSync(new URL(`../../Fixtures/api/${fixture}.json`, import.meta.url)))
    return
  }
  if (incoming.url.startsWith('/media/logo-oraki-') || incoming.url.startsWith('/media/icone-oraki-')) {
    outgoing.writeHead(200, { 'content-type': 'image/png' }).end(LOGO)
    return
  }
  if (incoming.headers['x-scenario'] === 'metas' && WITH_METAS.includes(incoming.url)) {
    fetch(new URL(incoming.url, API))
      .then((response) => response.json())
      .then((detail) => {
        outgoing.writeHead(200, { 'content-type': 'application/json' })
        outgoing.end(JSON.stringify({ ...detail, ...METAS }))
      })
      .catch(() => outgoing.writeHead(502).end())
    return
  }
  const proxied = forward(
    { host: API.hostname, port: API.port, method: incoming.method, path: incoming.url, headers: { ...incoming.headers, host: API.host } },
    (response) => {
      outgoing.writeHead(response.statusCode ?? 502, response.headers)
      response.pipe(outgoing)
    },
  )
  proxied.on('error', () => outgoing.writeHead(502).end())
  incoming.pipe(proxied)
}).listen(PORT, () => console.log(`Fausse API sur ${PORT}, relais vers ${API.origin}`))
