/**
 * Thrown when the API answers with a non-2xx status.
 *
 * The status is kept separate from the message so a caller can tell a 403
 * (the Gate is not configured) from a 422 (the request was wrong) without
 * matching on prose.
 */
export class SeoApiError extends Error {
  constructor(
    message: string,
    readonly status: number,
    readonly body: unknown,
  ) {
    super(message)
    this.name = 'SeoApiError'
  }

  /** The Gate denies everyone until the application defines it. */
  get isForbidden(): boolean {
    return this.status === 403
  }

  get isValidation(): boolean {
    return this.status === 422
  }

  /**
   * Laravel's validation error shape: `{ errors: { field: [message, …] } }`.
   * The top-level `message` on a 422 is often a generic "The given data was
   * invalid.", not the specific reason a field failed — that specific reason
   * lives here instead.
   */
  fieldErrors(): Record<string, string[]> | null {
    if (this.body === null || typeof this.body !== 'object' || !('errors' in this.body)) return null

    const { errors } = this.body as { errors: unknown }

    if (errors === null || typeof errors !== 'object') return null

    return errors as Record<string, string[]>
  }

  /** The first message for one field, or the generic error message. */
  fieldError(field: string): string {
    return this.fieldErrors()?.[field]?.[0] ?? this.message
  }
}

export class SeoTimeoutError extends Error {
  constructor(readonly milliseconds: number) {
    super(`The SEO API did not respond within ${milliseconds}ms.`)
    this.name = 'SeoTimeoutError'
  }
}
