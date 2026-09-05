import assert from 'node:assert/strict'
import { test } from 'node:test'
import { effectScope, nextTick, ref } from 'vue'
import type { SeoClient } from '@duxbo/seo-core'
import { useMetaStore } from './useMetaStore.js'

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
    dynamicSettings: async () => ({ enabled: false, settings: {} }),
    updateDynamicSettings: async (settings) => ({ saved: Object.keys(settings) }),
    deleteDynamicSetting: async (key) => ({ cleared: key }),
    ...overrides,
  }
}

// `effectScope` runs the composable outside a component, which is what makes
// it testable with no renderer or DOM — Vue's reactivity system itself does
// not require either.

test('the reactive store reflects an edit made through set()', () => {
  const scope = effectScope()

  scope.run(() => {
    const { store, set } = useMetaStore(stubClient(), { type: 'post', id: 1 })

    set('title', 'Tiêu đề mới')

    assert.equal(store.draft.title, 'Tiêu đề mới')
    assert.equal(store.isDirty, true)
  })

  scope.stop()
})

test('a getter target is re-read when the underlying ref changes', async () => {
  const scope = effectScope()

  await scope.run(async () => {
    const id = ref(1)
    const { store, set } = useMetaStore(stubClient(), () => ({ type: 'post', id: id.value }))

    set('title', 'Bẩn cho bài 1')
    assert.equal(store.isDirty, true)

    id.value = 2
    await nextTick()

    // A store scoped to post 1 must not leak its dirty title into post 2.
    assert.equal(store.isDirty, false)
    assert.notEqual(store.draft.title, 'Bẩn cho bài 1')
  })

  scope.stop()
})

test('a static target never rebuilds, matching a plain object call site', async () => {
  const scope = effectScope()

  await scope.run(async () => {
    const { store, set } = useMetaStore(stubClient(), { type: 'post', id: 1 })

    set('title', 'Giữ nguyên')
    await nextTick()

    assert.equal(store.draft.title, 'Giữ nguyên')
  })

  scope.stop()
})

test('save() clears the dirty flag and updates resolved', async () => {
  const scope = effectScope()

  await scope.run(async () => {
    const { store, set, save } = useMetaStore(stubClient(), { type: 'post', id: 1 })

    set('title', 'Mới')
    await save()

    assert.equal(store.isDirty, false)
    assert.equal(store.stored?.title, 'Mới')
  })

  scope.stop()
})

test('reset() returns the draft to what was loaded', async () => {
  const scope = effectScope()

  await scope.run(async () => {
    const { store, load, set, reset } = useMetaStore(
      stubClient({
        getMeta: async () => ({ stored: { title: 'Gốc' }, resolved: {}, locales: [] }),
      }),
      { type: 'post', id: 1 },
    )

    await load()
    set('title', 'Đã sửa')
    reset()

    assert.equal(store.draft.title, 'Gốc')
    assert.equal(store.isDirty, false)
  })

  scope.stop()
})

test('using the composable outside a component does not throw', () => {
  // getCurrentInstance() is null here; onUnmounted must be skipped rather
  // than throwing, since this is exactly how the tests above call it.
  assert.doesNotThrow(() => {
    useMetaStore(stubClient(), { type: 'post', id: 1 })
  })
})
