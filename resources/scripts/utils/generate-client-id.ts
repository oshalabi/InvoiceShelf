let counter = 0

/**
 * Local identity for a row that does not exist server-side yet — a new invoice
 * item, a new tax line. The server assigns the real numeric id on save (hence
 * `id: number | string` on DocumentItem/DocumentTax), so this only has to stay
 * unique within the page session, which a counter guarantees outright.
 *
 * Deliberately not `crypto.randomUUID()`: that is `[SecureContext]`-gated, so it
 * is undefined on any plain-HTTP origin that isn't localhost. That covers the
 * dev host (`http://invoiceshelf.test`) and self-hosted installs reached over a
 * hostname or LAN IP — where calling it threw during Pinia store construction
 * and took the document screens down with it. Nothing here needs randomness.
 */
export function generateClientId(): string {
  return `client-${++counter}`
}
