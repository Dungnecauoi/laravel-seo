import assert from 'node:assert/strict'
import { test } from 'node:test'
import { act, create } from 'react-test-renderer'
import type { SeoClient } from '@duxbo/seo-core'
import { useMetaStore } from './useMetaStore.js'
import type { MetaStore } from '@duxbo/seo-core'

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

function Probe({ client, id, onStore }: { client: SeoClient; id: number; onStore: (s: MetaStore) => void }) {
  const store = useMetaStore(client, { type: 'post', id })
  onStore(store)
  return null
}

test('the returned store reflects an edit after onChange fires', async () => {
  let latest: MetaStore | null = null
  const client = stubClient()

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<Probe client={client} id={1} onStore={(s) => (latest = s)} />)
  })

  await act(async () => {
    latest!.set('title', 'Tiêu đề mới')
  })

  assert.equal(latest!.draft.title, 'Tiêu đề mới')
  assert.equal(latest!.isDirty, true)

  renderer!.unmount()
})

test('changing the target identity produces a fresh, undirtied store', async () => {
  const seen: MetaStore[] = []
  const client = stubClient()

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<Probe client={client} id={1} onStore={(s) => seen.push(s)} />)
  })

  await act(async () => {
    seen[seen.length - 1]!.set('title', 'Bẩn')
  })

  act(() => {
    renderer!.update(<Probe client={client} id={2} onStore={(s) => seen.push(s)} />)
  })

  const afterSwitch = seen[seen.length - 1]!

  // A store scoped to post 1 must not leak its dirty title into post 2.
  assert.equal(afterSwitch.isDirty, false)
  assert.notEqual(afterSwitch.draft.title, 'Bẩn')

  renderer!.unmount()
})

test('unmounting cancels a pending debounced analysis', async () => {
  let calls = 0
  const client = stubClient({
    analyze: async () => {
      calls++
      return { score: 1, locale: null, results: [] }
    },
  })

  let latest: MetaStore | null = null
  let renderer: ReturnType<typeof create>

  await act(async () => {
    renderer = create(<Probe client={client} id={1} onStore={(s) => (latest = s)} />)
  })

  act(() => {
    latest!.analyze('nội dung')
  })

  renderer!.unmount()

  await new Promise((resolve) => setTimeout(resolve, 700))

  assert.equal(calls, 0)
})
