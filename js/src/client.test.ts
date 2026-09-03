import assert from 'node:assert/strict'
import { test } from 'node:test'
import { createSeoClient, CONTRACT_VERSION } from './client.js'
import { SeoApiError, SeoTimeoutError } from './errors.js'

function fakeFetch(
  handler: (url: string, init: RequestInit) => { status?: number; body?: unknown; headers?: Record<string, string> },
): typeof globalThis.fetch {
  return (async (input: RequestInfo | URL, init: RequestInit = {}) => {
    const { status = 200, body = {}, headers = {} } = handler(String(input), init)

    return new Response(status === 204 ? null : JSON.stringify(body), {
      status,
      headers: { 'Content-Type': 'application/json', ...headers },
    })
  }) as typeof globalThis.fetch
}

test('it builds the endpoint from the base URL and prefix', async () => {
  let seen = ''

  const client = createSeoClient({
    baseUrl: 'https://example.com/',
    fetch: fakeFetch((url) => {
      seen = url
      return { body: { title: 'x' } }
    }),
  })

  await client.resolve('/bai-viet', { format: 'next' })

  // No doubled slash from the trailing one on baseUrl.
  assert.equal(seen, 'https://example.com/api/seo/v1/resolve?url=%2Fbai-viet&format=next')
})

test('it sends the bearer token when one is given', async () => {
  let auth: string | null = null

  const client = createSeoClient({
    baseUrl: 'https://example.com',
    token: 'secret',
    fetch: fakeFetch((_url, init) => {
      auth = (init.headers as Record<string, string>).Authorization ?? null
      return { body: {} }
    }),
  })

  await client.resolve('/x')

  assert.equal(auth, 'Bearer secret')
})

test('a non-2xx response carries its status, not just a message', async () => {
  const client = createSeoClient({
    baseUrl: 'https://example.com',
    fetch: fakeFetch(() => ({ status: 403, body: { message: 'Forbidden.' } })),
  })

  // A caller needs to tell "the Gate is not configured" from "the request was
  // wrong" without matching on prose.
  await assert.rejects(
    () => client.resolve('/x'),
    (error: unknown) => {
      assert.ok(error instanceof SeoApiError)
      assert.equal(error.status, 403)
      assert.equal(error.isForbidden, true)
      assert.equal(error.isValidation, false)
      return true
    },
  )
})

test('a contract mismatch warns once rather than failing silently', async () => {
  const warnings: unknown[] = []
  const original = console.warn
  console.warn = (...args: unknown[]) => warnings.push(args[0])

  try {
    const client = createSeoClient({
      baseUrl: 'https://example.com',
      fetch: fakeFetch(() => ({ body: {}, headers: { 'X-Seo-Contract': '99' } })),
    })

    await client.resolve('/a')
    await client.resolve('/b')

    assert.equal(warnings.length, 1)
    assert.match(String(warnings[0]), new RegExp(`v${CONTRACT_VERSION}`))
  } finally {
    console.warn = original
  }
})

test('a request that never answers is aborted, not left running', async () => {
  const client = createSeoClient({
    baseUrl: 'https://example.com',
    timeout: 20,
    fetch: ((_input: RequestInfo | URL, init: RequestInit = {}) =>
      new Promise((_resolve, reject) => {
        init.signal?.addEventListener('abort', () => {
          const error = new Error('aborted')
          error.name = 'AbortError'
          reject(error)
        })
      })) as typeof globalThis.fetch,
  })

  await assert.rejects(() => client.resolve('/x'), SeoTimeoutError)
})

test('the 404 listing is unwrapped from its data envelope', async () => {
  const client = createSeoClient({
    baseUrl: 'https://example.com',
    fetch: fakeFetch(() => ({ body: { data: [{ id: 1, path: '/x', hits: 3 }] } })),
  })

  const entries = await client.notFound(10)

  assert.equal(entries.length, 1)
  assert.equal(entries[0]?.hits, 3)
})
