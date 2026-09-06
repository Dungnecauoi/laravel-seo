import assert from 'node:assert/strict'
import { test } from 'node:test'
import { act, create } from 'react-test-renderer'
import type { AiToolCallsResponse, SeoClient } from '@duxbo/seo-core'
import { SeoAiToolCalls } from './SeoAiToolCalls.js'

function stubClient(aiToolCalls: () => Promise<AiToolCallsResponse>): SeoClient {
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
    auditHistory: async () => ({ data: [], meta: { currentPage: 1, lastPage: 1, total: 0 } }),
    internalLinks: async () => ({ exposedTypes: [], type: null, data: [], meta: null }),
    searchConsoleStats: async () => ({ days: 30, totalClicks: 0, totalImpressions: 0, data: [] }),
    indexNowLog: async () => ({ data: [] }),
    aiTools: async () => ({ tools: [] }),
    aiToolCalls,
  }
}

test('shows the empty state with no tool calls', async () => {
  const client = stubClient(async () => ({ data: [], meta: { currentPage: 1, lastPage: 1, total: 0 } }))

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<SeoAiToolCalls client={client} />)
  })

  assert.ok(JSON.stringify(renderer!.toJSON()).includes('Chưa có lần gọi tool AI'))
})

test('renders a call with its tool name, risk tier and status', async () => {
  const client = stubClient(async () => ({
    data: [
      {
        id: 1,
        tool: 'seo.redirects.delete',
        riskTier: 'destructive',
        status: 'applied',
        proposalId: 'abc-123',
        preview: null,
        input: { id: 1 },
        output: { deleted: true },
        scope: null,
        createdAt: '2026-01-01T00:00:00Z',
        appliedAt: '2026-01-01T00:00:05Z',
      },
    ],
    meta: { currentPage: 1, lastPage: 1, total: 1 },
  }))

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<SeoAiToolCalls client={client} />)
  })

  const json = JSON.stringify(renderer!.toJSON())
  assert.ok(json.includes('seo.redirects.delete'))
  assert.ok(json.includes('destructive'))
  assert.ok(json.includes('Đã áp dụng'))
})

test('shows a proposed call as pending, not applied', async () => {
  const client = stubClient(async () => ({
    data: [
      {
        id: 1,
        tool: 'seo.settings.set',
        riskTier: 'write',
        status: 'proposed',
        proposalId: 'abc-123',
        preview: null,
        input: { key: 'verification.google', value: 'x' },
        output: null,
        scope: null,
        createdAt: '2026-01-01T00:00:00Z',
        appliedAt: null,
      },
    ],
    meta: { currentPage: 1, lastPage: 1, total: 1 },
  }))

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<SeoAiToolCalls client={client} />)
  })

  const json = JSON.stringify(renderer!.toJSON())
  assert.ok(json.includes('Đã đề xuất'))
  assert.ok(!json.includes('Đã áp dụng'))
})
