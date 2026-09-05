import assert from 'node:assert/strict'
import { test } from 'node:test'
import { act, create } from 'react-test-renderer'
import type { DynamicSettingsResponse, SeoClient, SettingsResponse } from '@duxbo/seo-core'
import { SeoSettings } from './SeoSettings.js'

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
    settings: async () => base,
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

const base: SettingsResponse = {
  seoEnabled: true,
  indexableEnvironments: ['production'],
  currentEnvironment: 'production',
  apiEnabled: true,
  panelEnabled: true,
  exposedModels: ['post'],
  allowedHosts: [],
  sitemapSourceCount: 2,
  aiDriver: 'claude',
  aiBudget: 100000,
  analysisRateLimit: '30,1',
  supportedLocales: ['vi', 'en'],
}

const enabledDynamic: DynamicSettingsResponse = {
  enabled: true,
  settings: {
    enabled: { value: true, default: true, overridden: false, secret: false },
    site_name: { value: 'Trang Của Tôi', default: 'Trang Của Tôi', overridden: false, secret: false },
    'verification.google': { value: 'abc123', default: null, overridden: true, secret: false },
    'robots.block_ai_crawlers': { value: false, default: false, overridden: false, secret: false },
    'schema.organization.sameAs': { value: ['https://facebook.com/x'], default: [], overridden: true, secret: false },
    'search_console.client_secret': { is_set: true, overridden: true, secret: true },
    'search_console.refresh_token': { is_set: false, overridden: false, secret: true },
  },
}

test('a disabled master switch renders the demo-domain warning pill', async () => {
  const client = stubClient({ settings: async () => ({ ...base, seoEnabled: false }) })

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<SeoSettings client={client} />)
  })

  assert.ok(JSON.stringify(renderer!.toJSON()).includes('Tắt — noindex toàn site'))
})

test('an enabled master switch renders the ok pill, not the warning', async () => {
  const client = stubClient()

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<SeoSettings client={client} />)
  })

  const json = JSON.stringify(renderer!.toJSON())
  assert.ok(json.includes('claude'))
  assert.ok(!json.includes('Tắt — noindex toàn site'))
})

test('the edit form is hidden when dynamic settings are disabled', async () => {
  const client = stubClient()

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<SeoSettings client={client} />)
  })

  const json = JSON.stringify(renderer!.toJSON())
  assert.ok(json.includes('Cấu hình động đang tắt'))
  assert.ok(!json.includes('Chỉnh cấu hình'))
})

test('the edit form pre-fills from the fetched dynamic settings', async () => {
  const client = stubClient({ dynamicSettings: async () => enabledDynamic })

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<SeoSettings client={client} />)
  })

  // findByProps({ value }) is ambiguous here: the Field wrapper component
  // and the host <input> it renders both carry the same `value` prop, so
  // this looks for the host element specifically.
  const input = renderer!.root.findAll((node) => node.type === 'input' && node.props.value === 'abc123')[0]
  assert.equal(input?.props.value, 'abc123')
})

test('a secret field shows its is_set status but never its value', async () => {
  const client = stubClient({ dynamicSettings: async () => enabledDynamic })

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<SeoSettings client={client} />)
  })

  const json = JSON.stringify(renderer!.toJSON())
  assert.ok(json.includes('Đã đặt'))
  assert.ok(json.includes('Chưa đặt'))
})

test('submitting sends the edited value and omits an untouched blank secret', async () => {
  const client = stubClient({ dynamicSettings: async () => enabledDynamic })
  let sent: Record<string, unknown> | null = null

  client.updateDynamicSettings = async (settings) => {
    sent = settings
    return { saved: Object.keys(settings) }
  }

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<SeoSettings client={client} />)
  })

  const input = renderer!.root.findAll((node) => node.type === 'input' && node.props.value === 'abc123')[0]

  await act(async () => {
    input?.props.onChange({ target: { value: 'xyz999' } })
  })

  const form = renderer!.root.findByType('form')

  await act(async () => {
    await form.props.onSubmit({ preventDefault: () => {} })
  })

  assert.equal((sent as unknown as Record<string, unknown>)['verification.google'], 'xyz999')
  // The refresh token secret was never touched (still blank) — must not be
  // sent as an empty string, which would overwrite it with nothing.
  assert.ok(!('search_console.refresh_token' in (sent as unknown as Record<string, unknown>)))
})

test('the sameAs textarea round-trips as a newline-joined string and splits back into a list on submit', async () => {
  const client = stubClient({ dynamicSettings: async () => enabledDynamic })
  let sent: Record<string, unknown> | null = null

  client.updateDynamicSettings = async (settings) => {
    sent = settings
    return { saved: Object.keys(settings) }
  }

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<SeoSettings client={client} />)
  })

  const textarea = renderer!.root.findAll(
    (node) => node.type === 'textarea' && node.props.value === 'https://facebook.com/x',
  )[0]

  await act(async () => {
    textarea?.props.onChange({ target: { value: 'https://facebook.com/x\nhttps://youtube.com/x' } })
  })

  const form = renderer!.root.findByType('form')

  await act(async () => {
    await form.props.onSubmit({ preventDefault: () => {} })
  })

  assert.deepEqual((sent as unknown as Record<string, unknown>)['schema.organization.sameAs'], [
    'https://facebook.com/x',
    'https://youtube.com/x',
  ])
})
