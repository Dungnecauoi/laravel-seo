import type { SeoClient } from './client.js'
import type { AnalysisReport, SeoData } from './types.js'

export interface MetaStoreTarget {
  type: string
  id: string | number
  locale?: string
}

export interface MetaStoreOptions {
  /** Milliseconds to wait after the last edit before re-scoring. Default 600. */
  analysisDebounce?: number
  /** Called whenever the store's state changes. */
  onChange?: (store: MetaStore) => void
}

/**
 * One editing session for one record.
 *
 * This is the part worth writing once. A panel needs to know what the user
 * changed (not merely what the fields hold — a field equal to the stored value
 * is not a change), when to re-score without a request per keystroke, and how
 * to abandon an in-flight analysis whose input is already stale.
 */
export interface MetaStore {
  readonly draft: SeoData
  readonly stored: SeoData | null
  /** What will be published, including everything the fallback chain supplies. */
  readonly resolved: SeoData | null
  readonly report: AnalysisReport | null
  readonly isDirty: boolean
  readonly isLoading: boolean
  readonly isSaving: boolean
  readonly isAnalyzing: boolean
  readonly error: Error | null

  load(): Promise<void>
  set<K extends keyof SeoData>(field: K, value: SeoData[K]): void
  reset(): void
  save(): Promise<void>
  analyze(content: string): void
  destroy(): void
}

export function createMetaStore(
  client: SeoClient,
  target: MetaStoreTarget,
  options: MetaStoreOptions = {},
): MetaStore {
  const { analysisDebounce = 600, onChange } = options

  let draft: SeoData = {}
  let stored: SeoData | null = null
  let resolved: SeoData | null = null
  let report: AnalysisReport | null = null
  let isLoading = false
  let isSaving = false
  let isAnalyzing = false
  let error: Error | null = null

  let debounceTimer: ReturnType<typeof setTimeout> | undefined
  // Only the newest analysis may write the report: without this, a slow
  // earlier request lands last and shows a score for text nobody is looking at.
  let analysisToken = 0

  const store: MetaStore = {
    get draft() {
      return draft
    },
    get stored() {
      return stored
    },
    get resolved() {
      return resolved
    },
    get report() {
      return report
    },
    get isDirty() {
      return isDirty()
    },
    get isLoading() {
      return isLoading
    },
    get isSaving() {
      return isSaving
    },
    get isAnalyzing() {
      return isAnalyzing
    },
    get error() {
      return error
    },

    async load() {
      isLoading = true
      error = null
      notify()

      try {
        const response = await client.getMeta(target.type, target.id, target.locale)
        stored = response.stored
        resolved = response.resolved
        draft = { ...(response.stored ?? {}) }
      } catch (e) {
        error = e instanceof Error ? e : new Error(String(e))
      } finally {
        isLoading = false
        notify()
      }
    },

    set(field, value) {
      draft = { ...draft, [field]: value }
      notify()
    },

    reset() {
      draft = { ...(stored ?? {}) }
      notify()
    },

    async save() {
      isSaving = true
      error = null
      notify()

      try {
        const payload = target.locale ? { ...draft, locale: target.locale } : draft
        const response = await client.saveMeta(target.type, target.id, payload)

        stored = { ...draft }
        resolved = response.resolved
      } catch (e) {
        error = e instanceof Error ? e : new Error(String(e))
      } finally {
        isSaving = false
        notify()
      }
    },

    analyze(content) {
      // Debounced, because a request per keystroke is both slow and expensive.
      if (debounceTimer) clearTimeout(debounceTimer)

      debounceTimer = setTimeout(() => {
        const token = ++analysisToken
        isAnalyzing = true
        notify()

        void client
          .analyze({
            content,
            ...(draft.focusKeyword !== undefined ? { keyword: draft.focusKeyword } : {}),
            ...(draft.title !== undefined ? { title: draft.title } : {}),
            ...(draft.description !== undefined ? { description: draft.description } : {}),
            ...(target.locale !== undefined ? { locale: target.locale } : {}),
          })
          .then((result) => {
            if (token !== analysisToken) return
            report = result
          })
          .catch((e: unknown) => {
            if (token !== analysisToken) return
            error = e instanceof Error ? e : new Error(String(e))
          })
          .finally(() => {
            if (token !== analysisToken) return
            isAnalyzing = false
            notify()
          })
      }, analysisDebounce)
    },

    destroy() {
      if (debounceTimer) clearTimeout(debounceTimer)
      analysisToken++
    },
  }

  function isDirty(): boolean {
    const base = stored ?? {}
    const keys = new Set([...Object.keys(base), ...Object.keys(draft)])

    for (const key of keys) {
      const a = base[key as keyof SeoData]
      const b = draft[key as keyof SeoData]

      // Empty string and undefined both mean "not set", so switching between
      // them is not an edit and must not light up the save button.
      if (isBlank(a) && isBlank(b)) continue
      if (JSON.stringify(a) !== JSON.stringify(b)) return true
    }

    return false
  }

  function notify(): void {
    onChange?.(store)
  }

  return store
}

function isBlank(value: unknown): boolean {
  return value === undefined || value === null || value === ''
}
