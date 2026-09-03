import { type MaybeRefOrGetter, getCurrentInstance, onUnmounted, reactive, shallowRef, toValue, watch } from 'vue'
import { createMetaStore } from '@duxbo/seo-core'
import type { AnalysisReport, MetaStoreOptions, MetaStoreTarget, SeoClient, SeoData } from '@duxbo/seo-core'

/**
 * The readonly half of MetaStore — what `reactive()` wraps. Kept separate
 * from the full `MetaStore` type because `reactive()` copies plain data
 * properties, not the accessor methods (`load`, `set`, …), and typing the
 * result as the full interface would claim methods that are not there.
 */
export interface MetaStoreState {
  draft: SeoData
  stored: SeoData | null
  resolved: SeoData | null
  report: AnalysisReport | null
  isDirty: boolean
  isLoading: boolean
  isSaving: boolean
  isAnalyzing: boolean
  error: Error | null
}

export interface UseMetaStoreResult {
  store: MetaStoreState
  load: () => Promise<void>
  set: <K extends keyof SeoData>(field: K, value: SeoData[K]) => void
  reset: () => void
  save: () => Promise<void>
  analyze: (content: string) => void
}

/**
 * Wraps a MetaStore in Vue's reactivity.
 *
 * `createMetaStore` returns a plain object whose fields are getters — Vue's
 * `reactive()` cannot intercept those, so `store.value.isDirty` in a template
 * would never trigger a re-render. Instead, a `reactive()` shell is kept in
 * sync by copying the store's current values across on every `onChange`,
 * which is what makes plain property access work in templates.
 *
 * `target` accepts a plain object, a `ref`, or a getter
 * (`MaybeRefOrGetter<MetaStoreTarget>`). A plain object is what most call
 * sites want — `useMetaStore(client, { type: 'post', id: props.id })` — and
 * behaves correctly precisely because `setup()` runs once: on a static
 * target there is nothing to react to. Navigating between records inside the
 * same mounted component, without remounting it, needs `() => ({ type:
 * 'post', id: route.params.id })` so the store rebuilds when the id changes.
 */
export function useMetaStore(
  client: SeoClient,
  target: MaybeRefOrGetter<MetaStoreTarget>,
  options: MetaStoreOptions = {},
): UseMetaStoreResult {
  function build() {
    return createMetaStore(client, toValue(target), {
      ...options,
      // `state` is declared below with `const`, but this callback only ever
      // runs later, after useMetaStore has finished executing — by then the
      // binding is initialised, so the forward reference is safe.
      onChange: () => Object.assign(state, snapshot(raw)),
    })
  }

  let raw = build()
  const state = reactive(snapshot(raw)) as MetaStoreState

  const key = shallowRef(cacheKey(toValue(target)))

  watch(
    () => cacheKey(toValue(target)),
    (next) => {
      if (next === key.value) return

      key.value = next
      raw.destroy()
      raw = build()
      Object.assign(state, snapshot(raw))
    },
  )

  // onUnmounted throws when called outside a component's setup(). The
  // composable is also called directly in tests, which is a legitimate use
  // the guard has to allow rather than crash on.
  if (getCurrentInstance()) {
    onUnmounted(() => raw.destroy())
  }

  return {
    store: state,
    load: () => raw.load(),
    set: (field, value) => raw.set(field, value),
    reset: () => raw.reset(),
    save: () => raw.save(),
    analyze: (content) => raw.analyze(content),
  }
}

function cacheKey(target: MetaStoreTarget): string {
  return `${target.type}:${String(target.id)}:${target.locale ?? ''}`
}

function snapshot(store: {
  draft: SeoData
  stored: SeoData | null
  resolved: SeoData | null
  report: AnalysisReport | null
  isDirty: boolean
  isLoading: boolean
  isSaving: boolean
  isAnalyzing: boolean
  error: Error | null
}): MetaStoreState {
  return {
    draft: store.draft,
    stored: store.stored,
    resolved: store.resolved,
    report: store.report,
    isDirty: store.isDirty,
    isLoading: store.isLoading,
    isSaving: store.isSaving,
    isAnalyzing: store.isAnalyzing,
    error: store.error,
  }
}
