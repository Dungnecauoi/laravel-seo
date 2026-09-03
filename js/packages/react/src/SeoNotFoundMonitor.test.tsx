import assert from 'node:assert/strict'
import { test } from 'node:test'
import { act, create } from 'react-test-renderer'
import { SeoApiError } from '@duxbo/seo-core'
import type { NotFoundEntry, SeoClient } from '@duxbo/seo-core'
import { SeoNotFoundMonitor } from './SeoNotFoundMonitor.js'

function stubClient(overrides: Partial<SeoClient> = {}): SeoClient {
  return {
    resolve: async () => ({ url: '/x', locale: null }),
    analyze: async () => ({ score: 0, locale: null, results: [] }),
    getMeta: async () => ({ stored: null, resolved: {}, locales: [] }),
    saveMeta: async (_t, _i, data) => ({ resolved: data }),
    deleteMeta: async () => {},
    notFound: async () => [],
    deleteNotFound: async () => {},
    pruneNotFound: async () => ({ deleted: 0 }),
    convertNotFoundToRedirect: async () => ({ id: 0 }),
    dashboard: async () => ({
      seoEnabled: true,
      totalRecords: 0,
      missingByType: {},
      totalMissing: 0,
      activeRedirects: 0,
      notFoundCount: 0,
      sitemapSources: 0,
      exposedTypes: [],
    }),
    content: async () => ({ exposedTypes: [], type: null, data: [], meta: null }),
    settings: async () => ({
      seoEnabled: true,
      indexableEnvironments: [],
      currentEnvironment: 'testing',
      apiEnabled: true,
      panelEnabled: false,
      exposedModels: [],
      allowedHosts: [],
      sitemapSourceCount: 0,
      aiDriver: 'null',
      aiBudget: 0,
      analysisRateLimit: '30,1',
      supportedLocales: [],
    }),
    redirects: async () => ({ data: [], meta: { currentPage: 1, lastPage: 1, total: 0 } }),
    createRedirect: async () => ({ id: 1 }),
    toggleRedirect: async () => ({ isActive: true }),
    deleteRedirect: async () => {},
    ...overrides,
  }
}

const escapedEntry: NotFoundEntry = {
  id: 1,
  path: '/&lt;script&gt;alert(1)&lt;/script&gt;',
  hits: 3,
  referrer: null,
  user_agent: null,
  first_seen_at: null,
  last_seen_at: '2026-01-01 00:00:00',
}

test('the already-escaped path is passed straight through, not escaped again', async () => {
  const client = stubClient({ notFound: async () => [escapedEntry] })

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<SeoNotFoundMonitor client={client} />)
  })

  const cell = renderer!.root.findAll((node) => Boolean(node.props.dangerouslySetInnerHTML))[0]
  assert.equal(cell?.props.dangerouslySetInnerHTML.__html, '/&lt;script&gt;alert(1)&lt;/script&gt;')
})

test('pruning sends the day threshold and reloads the list', async () => {
  let pruned: number | undefined
  let listed = 0

  const client = stubClient({
    notFound: async () => {
      listed++
      return []
    },
    pruneNotFound: async (days) => {
      pruned = days
      return { deleted: 2 }
    },
  })

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<SeoNotFoundMonitor client={client} />)
  })

  const form = renderer!.root.findByType('form')

  await act(async () => {
    await form.props.onSubmit({ preventDefault: () => {} })
  })

  assert.equal(pruned, 90)
  assert.equal(listed, 2)
})

test('converting a 404 to a redirect uses the typed target and clears the row on success', async () => {
  let converted: { id: number; target: string } | null = null

  const client = stubClient({
    notFound: async () => [escapedEntry],
    convertNotFoundToRedirect: async (id, target) => {
      converted = { id, target }
      return { id: 5 }
    },
  })

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<SeoNotFoundMonitor client={client} />)
  })

  const targetInput = renderer!.root.findByProps({ placeholder: 'Chuyển tới…' })

  await act(async () => {
    targetInput.props.onChange({ target: { value: '/trang-moi' } })
  })

  const convertButton = renderer!.root.findByProps({ children: 'Tạo redirect' })

  await act(async () => {
    await convertButton.props.onClick()
  })

  assert.deepEqual(converted, { id: 1, target: '/trang-moi' })
})

test('an unsafe redirect target from the conversion action shows the specific reason', async () => {
  const client = stubClient({
    notFound: async () => [escapedEntry],
    convertNotFoundToRedirect: async () => {
      throw new SeoApiError('The given data was invalid.', 422, {
        errors: { target: ['The redirect target is not on an allowed host.'] },
      })
    },
  })

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<SeoNotFoundMonitor client={client} />)
  })

  const targetInput = renderer!.root.findByProps({ placeholder: 'Chuyển tới…' })

  await act(async () => {
    targetInput.props.onChange({ target: { value: 'https://evil.example' } })
  })

  const convertButton = renderer!.root.findByProps({ children: 'Tạo redirect' })

  await act(async () => {
    await convertButton.props.onClick()
  })

  assert.ok(JSON.stringify(renderer!.toJSON()).includes('The redirect target is not on an allowed host.'))
})
