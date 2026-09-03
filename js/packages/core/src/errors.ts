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
}

export class SeoTimeoutError extends Error {
  constructor(readonly milliseconds: number) {
    super(`The SEO API did not respond within ${milliseconds}ms.`)
    this.name = 'SeoTimeoutError'
  }
}
