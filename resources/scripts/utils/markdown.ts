import DOMPurify from 'dompurify'

/**
 * Sanitize a raw HTML string with DOMPurify's default browser profile
 * (strips <script>, event handlers, javascript: URLs, and every other HTML
 * vector). Use this for HTML that originates from the server, a third-party
 * module registry, or the update server before binding it via v-html — the
 * `BaseSanitizedHtml` component wraps this so feature code never touches
 * v-html directly.
 */
export function sanitizeHtml(html: string | null | undefined): string {
  if (!html) {
    return ''
  }

  return DOMPurify.sanitize(html)
}
