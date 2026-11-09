import { readFileSync } from 'node:fs'

/**
 * Libellés d'une langue, lus dans translations/messages.<langue>.yaml, par clé à points (`nav.home`). Lecteur limité à la
 * forme de ces fichiers : clés imbriquées par indentation, valeurs entre apostrophes.
 */
export const messages = (locale: string): Record<string, string> => {
  const parents: string[] = []
  const entries: Record<string, string> = {}
  for (const line of readFileSync(`translations/messages.${locale}.yaml`, 'utf8').split('\n')) {
    const match = /^( *)([^:]+):(?: '(.*)')?$/.exec(line)
    if (!match) continue
    const [, indent, key, value] = match
    parents.length = indent.length / 4
    if (value === undefined) parents.push(key)
    else entries[[...parents, key].join('.')] = value.replaceAll("''", "'")
  }

  return entries
}
