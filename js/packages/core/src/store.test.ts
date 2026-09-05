import assert from 'node:assert/strict'
import { test } from 'node:test'
import type { SeoClient } from './client.js'
import { createMetaStore } from './store.js'
import type { AnalysisReport, MetaResponse, SeoData } from './types.js'

function stubClient(overrides: Partial<SeoClient> = {}): SeoClient {
  return {
    resolve: async () => ({ url: '/x', locale: null }),
    analyze: async (): Promise<AnalysisReport> => ({ score: 50, locale: null, results: [] }),
    getMeta: async (): Promise<MetaResponse> => ({ stored: null, resolved: {}, locales: [] }),
    saveMeta: async (_t, _i, data): Promise<{ resolved: SeoData }> => ({ resolved: data }),
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
    dynamicSettings: async () => ({ enabled: false, settings: {} }),
    updateDynamicSettings: async (settings) => ({ saved: Object.keys(settings) }),
    deleteDynamicSetting: async (key) => ({ cleared: key }),
    ...overrides,
  }
}

const target = { type: 'post', id: 1 }

test('the draft starts from what was stored', async () => {
  const store = createMetaStore(
    stubClient({
      getMeta: async () => ({ stored: { title: 'Đã lưu' }, resolved: { title: 'Đã lưu' }, locales: [] }),
    }),
    target,
  )

  await store.load()

  assert.equal(store.draft.title, 'Đã lưu')
  assert.equal(store.isDirty, false)
})

test('editing a field marks the store dirty', async () => {
  const store = createMetaStore(stubClient(), target)
  await store.load()

  store.set('title', 'Mới')

  assert.equal(store.isDirty, true)
})

test('switching between empty and unset is not an edit', async () => {
  const store = createMetaStore(
    stubClient({
      getMeta: async () => ({ stored: { title: 'x' }, resolved: {}, locales: [] }),
    }),
    target,
  )

  await store.load()

  // Both mean "not set", so this must not light up the save button.
  store.set('description', '')

  assert.equal(store.isDirty, false)
})

test('reset returns the draft to what was stored', async () => {
  const store = createMetaStore(
    stubClient({
      getMeta: async () => ({ stored: { title: 'Gốc' }, resolved: {}, locales: [] }),
    }),
    target,
  )

  await store.load()
  store.set('title', 'Đã sửa')
  store.reset()

  assert.equal(store.draft.title, 'Gốc')
  assert.equal(store.isDirty, false)
})

test('saving clears the dirty flag', async () => {
  const store = createMetaStore(stubClient(), target)
  await store.load()

  store.set('title', 'Mới')
  await store.save()

  assert.equal(store.isDirty, false)
  assert.equal(store.stored?.title, 'Mới')
})

test('a failed request is surfaced rather than swallowed', async () => {
  const store = createMetaStore(
    stubClient({
      getMeta: async () => {
        throw new Error('nope')
      },
    }),
    target,
  )

  await store.load()

  assert.equal(store.error?.message, 'nope')
  assert.equal(store.isLoading, false)
})

test('typing repeatedly makes one analysis request, not one per keystroke', async () => {
  let calls = 0

  const store = createMetaStore(
    stubClient({
      analyze: async () => {
        calls++
        return { score: 80, locale: null, results: [] }
      },
    }),
    target,
    { analysisDebounce: 10 },
  )

  store.analyze('một')
  store.analyze('một hai')
  store.analyze('một hai ba')

  await new Promise((resolve) => setTimeout(resolve, 60))

  assert.equal(calls, 1)
  assert.equal(store.report?.score, 80)
})

test('a slow earlier analysis cannot overwrite a newer one', async () => {
  let call = 0

  const store = createMetaStore(
    stubClient({
      analyze: async () => {
        call++
        const isFirst = call === 1
        // The first request is slower, so without a guard it would land last
        // and show a score for text nobody is looking at any more.
        await new Promise((resolve) => setTimeout(resolve, isFirst ? 80 : 5))

        return { score: isFirst ? 10 : 90, locale: null, results: [] }
      },
    }),
    target,
    { analysisDebounce: 5 },
  )

  store.analyze('cũ')
  await new Promise((resolve) => setTimeout(resolve, 20))
  store.analyze('mới')
  await new Promise((resolve) => setTimeout(resolve, 140))

  assert.equal(store.report?.score, 90)
})

test('destroy cancels pending work', async () => {
  let calls = 0

  const store = createMetaStore(
    stubClient({
      analyze: async () => {
        calls++
        return { score: 1, locale: null, results: [] }
      },
    }),
    target,
    { analysisDebounce: 20 },
  )

  store.analyze('x')
  store.destroy()

  await new Promise((resolve) => setTimeout(resolve, 60))

  assert.equal(calls, 0)
})
