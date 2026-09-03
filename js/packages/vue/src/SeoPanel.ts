import { defineComponent, h, type PropType, watch } from 'vue'
import type { AnalysisReport, CheckResult, MetaStoreTarget, SeoClient } from '@duxbo/seo-core'
import { useMetaStore } from './useMetaStore.js'

/**
 * A working meta editor: title, description, focus keyword, and a live score
 * once `content` is supplied. A render function rather than a `.vue` SFC, so
 * the package builds with plain `tsc` — no bundler or SFC compiler needed for
 * a single component.
 *
 * Requires Tailwind configured in the host project, with this package's
 * output included in its content globs:
 *
 *     content: ['./node_modules/@duxbo/seo-vue/dist/*.js', …]
 *
 * Without that, every class here is purged and the panel renders unstyled.
 */
export const SeoPanel = defineComponent({
  name: 'SeoPanel',

  props: {
    client: { type: Object as PropType<SeoClient>, required: true },
    target: { type: Object as PropType<MetaStoreTarget>, required: true },
    content: { type: String, default: undefined },
    descriptionMin: { type: Number, default: 120 },
    descriptionMax: { type: Number, default: 158 },
  },

  emits: ['saved'],

  setup(props, { emit }) {
    const { store, load, set, reset, save, analyze } = useMetaStore(props.client, () => props.target)

    load()

    watch(
      () => [props.content, store.draft.title, store.draft.description, store.draft.focusKeyword] as const,
      ([content]) => {
        if (content !== undefined) analyze(content)
      },
    )

    async function handleSave() {
      await save()
      if (!store.error) emit('saved')
    }

    return () => {
      const descLength = store.draft.description?.length ?? 0
      const descInRange = descLength >= props.descriptionMin && descLength <= props.descriptionMax

      return h('div', { class: 'space-y-5 text-sm text-slate-900' }, [
        store.isLoading && h('p', { class: 'text-slate-500', role: 'status' }, 'Đang tải…'),

        store.error &&
          h(
            'p',
            { class: 'rounded-md border border-red-200 bg-red-50 px-3 py-2 text-red-700', role: 'alert' },
            store.error.message,
          ),

        field('Tiêu đề', [
          h('input', {
            type: 'text',
            value: store.draft.title ?? '',
            onInput: (e: Event) => set('title', (e.target as HTMLInputElement).value),
            class:
              'w-full rounded-md border border-slate-300 px-3 py-2 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500',
            placeholder: 'Để trống dùng giá trị mặc định',
          }),
          h('p', { class: 'mt-1 text-xs text-slate-400' }, `${(store.draft.title ?? '').length} ký tự`),
        ]),

        field('Mô tả', [
          h('textarea', {
            value: store.draft.description ?? '',
            onInput: (e: Event) => set('description', (e.target as HTMLTextAreaElement).value),
            rows: 3,
            class:
              'w-full rounded-md border border-slate-300 px-3 py-2 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500',
          }),
          h(
            'p',
            { class: `mt-1 text-xs ${descInRange ? 'text-emerald-600' : 'text-amber-600'}` },
            `${descLength} ký tự (khuyến nghị ${props.descriptionMin}–${props.descriptionMax})`,
          ),
        ]),

        field('Từ khoá chính', [
          h('input', {
            type: 'text',
            value: store.draft.focusKeyword ?? '',
            onInput: (e: Event) => set('focusKeyword', (e.target as HTMLInputElement).value),
            class:
              'w-full rounded-md border border-slate-300 px-3 py-2 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500',
          }),
        ]),

        props.content !== undefined && scorePanel(store.report, store.isAnalyzing),

        h('div', { class: 'flex items-center gap-3 border-t border-slate-100 pt-4' }, [
          h(
            'button',
            {
              type: 'button',
              onClick: handleSave,
              disabled: !store.isDirty || store.isSaving,
              class:
                'rounded-md bg-slate-900 px-4 py-2 font-medium text-white disabled:cursor-not-allowed disabled:bg-slate-300',
            },
            store.isSaving ? 'Đang lưu…' : 'Lưu',
          ),

          h(
            'button',
            {
              type: 'button',
              onClick: () => reset(),
              disabled: !store.isDirty,
              class:
                'rounded-md border border-slate-300 px-4 py-2 font-medium text-slate-700 disabled:cursor-not-allowed disabled:text-slate-300',
            },
            'Hoàn tác',
          ),

          store.isDirty && h('span', { class: 'text-xs text-amber-600' }, 'Có thay đổi chưa lưu'),
        ]),
      ])
    }
  },
})

function field(label: string, children: ReturnType<typeof h>[]) {
  return h('label', { class: 'block' }, [
    h('span', { class: 'mb-1 block font-medium text-slate-700' }, label),
    ...children,
  ])
}

function scorePanel(report: AnalysisReport | null, loading: boolean) {
  if (report === null) {
    return h('p', { class: 'text-xs text-slate-400' }, loading ? 'Đang phân tích…' : 'Chưa có điểm phân tích.')
  }

  const problems = report.results.filter((r) => r.status === 'fail' || r.status === 'warning')

  return h('div', { class: 'rounded-md border border-slate-200 p-4' }, [
    h('div', { class: 'flex items-center gap-3' }, [
      scoreRing(report.score),
      h('div', {}, [
        h('p', { class: 'font-medium text-slate-900' }, `${report.score}/100`),
        h(
          'p',
          { class: 'text-xs text-slate-500' },
          loading ? 'Đang cập nhật…' : `${problems.length} điểm cần chú ý`,
        ),
      ]),
    ]),

    problems.length > 0 &&
      h(
        'ul',
        { class: 'mt-3 space-y-1.5 border-t border-slate-100 pt-3' },
        problems.map((result) => checkRow(result)),
      ),
  ])
}

function checkRow(result: CheckResult) {
  const color = result.status === 'fail' ? 'text-red-600' : 'text-amber-600'
  const dot = result.status === 'fail' ? 'bg-red-500' : 'bg-amber-500'

  return h('li', { key: result.id, class: 'flex items-start gap-2 text-xs' }, [
    h('span', { class: `mt-1 h-1.5 w-1.5 shrink-0 rounded-full ${dot}` }),
    h('span', { class: color }, result.message),
  ])
}

function scoreRing(score: number) {
  const color = score >= 80 ? '#059669' : score >= 50 ? '#d97706' : '#dc2626'
  const circumference = 2 * Math.PI * 16
  const offset = circumference - (score / 100) * circumference

  return h('svg', { width: 40, height: 40, viewBox: '0 0 40 40', class: 'shrink-0' }, [
    h('circle', { cx: 20, cy: 20, r: 16, fill: 'none', stroke: '#e2e8f0', 'stroke-width': 4 }),
    h('circle', {
      cx: 20,
      cy: 20,
      r: 16,
      fill: 'none',
      stroke: color,
      'stroke-width': 4,
      'stroke-dasharray': circumference,
      'stroke-dashoffset': offset,
      'stroke-linecap': 'round',
      transform: 'rotate(-90 20 20)',
    }),
  ])
}
