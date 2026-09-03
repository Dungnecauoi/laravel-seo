import { defineComponent, h, type PropType, ref, watch } from 'vue'
import type { ContentListResponse, SeoClient } from '@duxbo/seo-core'

/**
 * Every record of one exposed type with its title resolved through the same
 * fallback chain a real page render uses — so an untitled record shows
 * exactly what search engines see, not just what was saved.
 */
export const SeoContentList = defineComponent({
  name: 'SeoContentList',

  props: {
    client: { type: Object as PropType<SeoClient>, required: true },
    /** Defaults to the first exposed type the API reports. */
    type: { type: String, default: undefined },
  },

  emits: ['edit'],

  setup(props, { emit }) {
    const activeType = ref<string | undefined>(props.type)
    const page = ref(1)
    const response = ref<ContentListResponse | null>(null)
    const error = ref<Error | null>(null)

    function load() {
      props.client
        .content(activeType.value, page.value)
        .then((data) => {
          response.value = data
          if (activeType.value === undefined && data.type) activeType.value = data.type
        })
        .catch((e: unknown) => {
          error.value = e instanceof Error ? e : new Error(String(e))
        })
    }

    watch(() => props.type, (t) => { activeType.value = t })
    watch([activeType, page], load, { immediate: true })

    function selectType(next: string) {
      activeType.value = next
      page.value = 1
    }

    return () => {
      if (error.value) {
        return h(
          'p',
          { class: 'rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700', role: 'alert' },
          error.value.message,
        )
      }

      const r = response.value

      if (!r) {
        return h('p', { class: 'text-sm text-slate-500', role: 'status' }, 'Đang tải…')
      }

      return h('div', { class: 'space-y-4 text-sm text-slate-900' }, [
        r.exposedTypes.length > 1 &&
          h(
            'div',
            { class: 'flex flex-wrap gap-2' },
            r.exposedTypes.map((t) =>
              h(
                'button',
                {
                  key: t,
                  type: 'button',
                  onClick: () => selectType(t),
                  class:
                    t === r.type
                      ? 'rounded-md bg-slate-900 px-3 py-1 text-xs font-medium text-white'
                      : 'rounded-md border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700',
                },
                t,
              ),
            ),
          ),

        r.type === null
          ? h('div', { class: 'rounded-md border border-slate-200 p-4 text-slate-500' }, [
              'Chưa có model nào được expose. Thêm vào ',
              h('code', 'seo.api.models'),
              ' trong ',
              h('code', 'config/seo.php'),
              '.',
            ])
          : r.data.length === 0
            ? h(
                'div',
                { class: 'rounded-md border border-dashed border-slate-300 p-6 text-center text-slate-400' },
                'Không có bản ghi nào.',
              )
            : h('div', {}, [
                h('div', { class: 'overflow-x-auto rounded-md border border-slate-200' }, [
                  h('table', { class: 'w-full text-left text-sm' }, [
                    h('thead', [
                      h('tr', { class: 'border-b border-slate-100 text-xs uppercase text-slate-400' }, [
                        h('th', { class: 'px-3 py-2 font-medium' }, 'Tiêu đề'),
                        h('th', { class: 'px-3 py-2 font-medium' }, 'Mô tả'),
                        h('th', { class: 'px-3 py-2 font-medium' }, 'Robots'),
                        h('th', { class: 'px-3 py-2' }),
                      ]),
                    ]),
                    h(
                      'tbody',
                      { class: 'divide-y divide-slate-100' },
                      r.data.map((row) =>
                        h('tr', { key: row.id }, [
                          h('td', { class: 'px-3 py-2' }, [
                            row.title ||
                              h(
                                'span',
                                { class: 'rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-700' },
                                'Chưa có tiêu đề',
                              ),
                            h('div', { class: 'mt-0.5 font-mono text-[11px] text-slate-400' }, row.url),
                          ]),
                          h('td', { class: 'px-3 py-2 text-slate-500' }, row.description ? truncate(row.description, 80) : '—'),
                          h(
                            'td',
                            { class: 'px-3 py-2' },
                            row.robots
                              ? h(
                                  'span',
                                  {
                                    class: row.robots.includes('noindex')
                                      ? 'rounded-full bg-red-50 px-2 py-0.5 text-xs text-red-700'
                                      : 'rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600',
                                  },
                                  row.robots,
                                )
                              : h('span', { class: 'text-slate-400' }, '—'),
                          ),
                          h(
                            'td',
                            { class: 'px-3 py-2 text-right' },
                            // r.type is never null in this branch — the
                            // `r.type === null` case is handled above.
                            h(
                              'button',
                              {
                                type: 'button',
                                onClick: () => emit('edit', r.type as string, row.id),
                                class: 'rounded-md border border-slate-300 px-2 py-1 text-xs font-medium text-slate-700',
                              },
                              'Sửa',
                            ),
                          ),
                        ]),
                      ),
                    ),
                  ]),
                ]),

                r.meta &&
                  r.meta.lastPage > 1 &&
                  h('div', { class: 'flex items-center gap-3 text-xs text-slate-500' }, [
                    h(
                      'button',
                      {
                        type: 'button',
                        disabled: page.value <= 1,
                        onClick: () => page.value--,
                        class:
                          'rounded-md border border-slate-300 px-2 py-1 font-medium text-slate-700 disabled:cursor-not-allowed disabled:opacity-40',
                      },
                      '← Trước',
                    ),
                    h('span', `Trang ${r.meta.currentPage}/${r.meta.lastPage} (${r.meta.total.toLocaleString('vi-VN')} bản ghi)`),
                    h(
                      'button',
                      {
                        type: 'button',
                        disabled: page.value >= r.meta.lastPage,
                        onClick: () => page.value++,
                        class:
                          'rounded-md border border-slate-300 px-2 py-1 font-medium text-slate-700 disabled:cursor-not-allowed disabled:opacity-40',
                      },
                      'Sau →',
                    ),
                  ]),
              ]),
      ])
    }
  },
})

function truncate(text: string, length: number): string {
  return text.length > length ? `${text.slice(0, length)}…` : text
}
