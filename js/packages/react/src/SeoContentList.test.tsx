import assert from 'node:assert/strict'
import { test } from 'node:test'
import { act, create } from 'react-test-renderer'
import type { ContentListResponse, SeoClient } from '@duxbo/seo-core'
import { SeoContentList } from './SeoContentList.js'

function stubClient(content: (type?: string, page?: number) => Promise<ContentListResponse>): SeoClient {
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
    content,
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
  }
}

test('shows an untitled record with the fallback pill rather than a blank cell', async () => {
  const client = stubClient(async () => ({
    exposedTypes: ['post'],
    type: 'post',
    data: [{ id: 1, title: null, description: null, robots: null, url: '/trong' }],
    meta: { currentPage: 1, lastPage: 1, total: 1 },
  }))

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<SeoContentList client={client} type="post" />)
  })

  assert.ok(JSON.stringify(renderer!.toJSON()).includes('Chưa có tiêu đề'))
})

test('onEdit receives the row type and id', async () => {
  const client = stubClient(async () => ({
    exposedTypes: ['post'],
    type: 'post',
    data: [{ id: 42, title: 'Bài viết', description: null, robots: null, url: '/bai-viet' }],
    meta: { currentPage: 1, lastPage: 1, total: 1 },
  }))

  const edits: [string, string | number][] = []
  let renderer: ReturnType<typeof create>

  await act(async () => {
    renderer = create(<SeoContentList client={client} type="post" onEdit={(t, id) => edits.push([t, id])} />)
  })

  const button = renderer!.root.findByProps({ children: 'Sửa' })

  act(() => {
    button.props.onClick()
  })

  assert.deepEqual(edits, [['post', 42]])
})

test('paging past the last page is disabled', async () => {
  const client = stubClient(async () => ({
    exposedTypes: ['post'],
    type: 'post',
    data: [{ id: 1, title: 'x', description: null, robots: null, url: '/x' }],
    meta: { currentPage: 1, lastPage: 1, total: 1 },
  }))

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<SeoContentList client={client} type="post" />)
  })

  // A single page renders no pager at all.
  assert.equal(renderer!.root.findAllByProps({ children: 'Sau →' }).length, 0)
})
