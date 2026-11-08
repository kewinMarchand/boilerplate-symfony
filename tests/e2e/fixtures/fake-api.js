// Fausse API de la phase @identite de make test-e2e (port 3232) : sert l'identité du site avec logo ou avec couleurs selon
// X-Scenario (fixtures de tests/Fixtures/api/), que l'API locale n'a pas par défaut, et relaie tout le reste à l'API réelle.
import { readFileSync } from 'node:fs'
import { createServer, request as forward } from 'node:http'

const PORT = 3232
const API = new URL(process.env.API_TARGET ?? 'http://localhost:8090')
const FIXTURES = { 'identite-logo': 'site-identity-logo', 'identite-couleurs': 'site-identity-couleurs' }
const LOGO = readFileSync(new URL('../../../public/icon-512.png', import.meta.url))

createServer((incoming, outgoing) => {
  const fixture = FIXTURES[incoming.headers['x-scenario']]
  if (incoming.url === '/api/site-identity' && fixture) {
    outgoing.writeHead(200, { 'content-type': 'application/json' })
    outgoing.end(readFileSync(new URL(`../../Fixtures/api/${fixture}.json`, import.meta.url)))
    return
  }
  if (incoming.url.startsWith('/media/logo-oraki-')) {
    outgoing.writeHead(200, { 'content-type': 'image/png' }).end(LOGO)
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
