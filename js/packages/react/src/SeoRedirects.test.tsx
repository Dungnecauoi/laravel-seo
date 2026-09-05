import assert from 'node:assert/strict'
import { test } from 'node:test'
import { act, create } from 'react-test-renderer'
import { SeoApiError } from '@duxbo/seo-core'
import type { RedirectEntry, RedirectInput, SeoClient } from '@duxbo/seo-core'
import { SeoRedirects } from './SeoRedirects.js'

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
    auditHistory: async () => ({ data: [], meta: { currentPage: 1, lastPage: 1, total: 0 } }),
    internalLinks: async () => ({ exposedTypes: [], type: null, data: [], meta: null }),
    searchConsoleStats: async () => ({ days: 30, totalClicks: 0, totalImpressions: 0, data: [] }),
    indexNowLog: async () => ({ data: [] }),
    ...overrides,
  }
}

const entry: RedirectEntry = {
  id: 7,
  source: '/cu',
  target: '/moi',
  type: 'exact',
  status: 301,
  isActive: true,
  locale: null,
  notes: null,
  hits: 4,
}

test('submitting the form creates a redirect and reloads the list', async () => {
  let created: RedirectInput | null = null
  let listed = 0

  const client = stubClient({
    createRedirect: async (input) => {
      created = input
      return { id: 9 }
    },
    redirects: async () => {
      listed++
      return { data: listed > 1 ? [entry] : [], meta: { currentPage: 1, lastPage: 1, total: listed > 1 ? 1 : 0 } }
    },
  })

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<SeoRedirects client={client} />)
  })

  const sourceInput = renderer!.root.findByProps({ placeholder: '/duong-dan-cu' })
  const targetInput = renderer!.root.findByProps({ placeholder: '/duong-dan-moi' })
  const form = renderer!.root.findByType('form')

  await act(async () => {
    sourceInput.props.onChange({ target: { value: '/cu' } })
    targetInput.props.onChange({ target: { value: '/moi' } })
  })

  await act(async () => {
    await form.props.onSubmit({ preventDefault: () => {} })
  })

  assert.equal((created as unknown as RedirectInput).source, '/cu')
  assert.ok(JSON.stringify(renderer!.toJSON()).includes('/cu'))
})

test('an unsafe redirect target surfaces the specific reason, not the generic message', async () => {
  const client = stubClient({
    createRedirect: async () => {
      throw new SeoApiError('The given data was invalid.', 422, {
        errors: { source: ['The redirect target is not on an allowed host.'] },
      })
    },
  })

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<SeoRedirects client={client} />)
  })

  const sourceInput = renderer!.root.findByProps({ placeholder: '/duong-dan-cu' })
  const form = renderer!.root.findByType('form')

  await act(async () => {
    sourceInput.props.onChange({ target: { value: '/khuyen-mai' } })
  })

  await act(async () => {
    await form.props.onSubmit({ preventDefault: () => {} })
  })

  assert.ok(JSON.stringify(renderer!.toJSON()).includes('The redirect target is not on an allowed host.'))
})

test('toggling a redirect calls the client and reloads', async () => {
  let toggled: number | null = null
  let listed = 0

  const client = stubClient({
    redirects: async () => {
      listed++
      return { data: [entry], meta: { currentPage: 1, lastPage: 1, total: 1 } }
    },
    toggleRedirect: async (id) => {
      toggled = id
      return { isActive: false }
    },
  })

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<SeoRedirects client={client} />)
  })

  const toggleButton = renderer!.root.findByProps({ children: 'Tắt' })

  await act(async () => {
    await toggleButton.props.onClick()
  })

  assert.equal(toggled, 7)
  assert.equal(listed, 2)
})
