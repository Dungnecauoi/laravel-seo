import { defineComponent, h, type PropType, reactive, ref } from 'vue'
import { SeoApiError } from '@duxbo/seo-core'
import type { NotFoundEntry, SeoClient } from '@duxbo/seo-core'

/**
 * The 404 log: prune stale entries, or turn one hit straight into a
 * redirect. `path`, `referrer` and `user_agent` on `NotFoundEntry` are
 * already HTML-escaped by the API — this is attacker-supplied text, and the
 * API escapes it so any consumer is safe by default, not just one that
 * happens to text-render it. Rendered here via `v-html` for that reason:
 * text-rendering an already-escaped string would show the literal entities
 * (`&lt;script&gt;`) instead of the path Google actually requested.
 */
export const SeoNotFoundMonitor = defineComponent({
  name: 'SeoNotFoundMonitor',

  props: {
    client: { type: Object as PropType<SeoClient>, required: true },
  },

  setup(props) {
    const entries = ref<NotFoundEntry[] | null>(null)
    const days = ref(90)
    const targets = reactive<Record<number, string>>({})
    const error = ref<string | null>(null)

    function reload() {
      props.client.notFound().then((data) => {
        entries.value = data
      })
    }

    reload()

    async function prune() {
      await props.client.pruneNotFound(days.value)
      reload()
    }

    async function convertToRedirect(id: number) {
      const target = targets[id]
      if (!target) return

      error.value = null

      try {
        await props.client.convertNotFoundToRedirect(id, target)
        reload()
      } catch (e) {
        error.value = e instanceof SeoApiError ? e.fieldError('target') : e instanceof Error ? e.message : String(e)
      }
    }

    async function remove(id: number) {
      if (!window.confirm('Xoá dòng này?')) return
      await props.client.deleteNotFound(id)
      reload()
    }

    return () =>
      h('div', { class: 'space-y-4 text-sm text-slate-900' }, [
        error.value &&
          h('p', { class: 'rounded-md border border-red-200 bg-red-50 px-3 py-2 text-red-700', role: 'alert' }, error.value),

        h(
          'form',
          {
            onSubmit: (e: Event) => {
              e.preventDefault()
              void prune()
            },
            class: 'flex flex-wrap items-center justify-between gap-3 rounded-md border border-slate-200 p-3',
          },
          [
            h('span', { class: 'text-xs text-slate-500' }, 'Bot quét thường tạo hàng loạt dòng vô nghĩa — dọn định kỳ.'),
            h('div', { class: 'flex items-center gap-2' }, [
              h('input', {
                type: 'number',
                min: 1,
                value: days.value,
                onInput: (e: Event) => { days.value = Number((e.target as HTMLInputElement).value) },
                class: 'w-20 rounded-md border border-slate-300 px-2 py-1 text-sm',
              }),
              h(
                'button',
                { type: 'submit', class: 'rounded-md border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700' },
                'Xoá mục cũ hơn (ngày)',
              ),
            ]),
          ],
        ),

        entries.value === null
          ? h('p', { class: 'text-slate-500', role: 'status' }, 'Đang tải…')
          : entries.value.length === 0
            ? h(
                'div',
                { class: 'rounded-md border border-dashed border-slate-300 p-6 text-center text-slate-400' },
                'Không có link 404 nào được ghi nhận.',
              )
            : h('div', { class: 'overflow-x-auto rounded-md border border-slate-200' }, [
                h('table', { class: 'w-full text-left text-sm' }, [
                  h('thead', [
                    h('tr', { class: 'border-b border-slate-100 text-xs uppercase text-slate-400' }, [
                      h('th', { class: 'px-3 py-2 font-medium' }, 'Đường dẫn'),
                      h('th', { class: 'px-3 py-2 font-medium' }, 'Lượt'),
                      h('th', { class: 'px-3 py-2 font-medium' }, 'Thấy lần cuối'),
                      h('th', { class: 'px-3 py-2' }),
                    ]),
                  ]),
                  h(
                    'tbody',
                    { class: 'divide-y divide-slate-100' },
                    entries.value.map((row) =>
                      h('tr', { key: row.id }, [
                        h('td', { class: 'px-3 py-2 font-mono text-xs', innerHTML: row.path }),
                        h('td', { class: 'px-3 py-2' }, row.hits.toLocaleString('vi-VN')),
                        h('td', { class: 'px-3 py-2 text-slate-500' }, row.last_seen_at ?? '—'),
                        h('td', { class: 'whitespace-nowrap px-3 py-2 text-right' }, [
                          h('input', {
                            type: 'text',
                            placeholder: 'Chuyển tới…',
                            value: targets[row.id] ?? '',
                            onInput: (e: Event) => { targets[row.id] = (e.target as HTMLInputElement).value },
                            class: 'mr-2 w-36 rounded-md border border-slate-300 px-2 py-1 text-xs',
                          }),
                          h(
                            'button',
                            {
                              type: 'button',
                              onClick: () => convertToRedirect(row.id),
                              class: 'mr-2 rounded-md border border-slate-300 px-2 py-1 text-xs font-medium text-slate-700',
                            },
                            'Tạo redirect',
                          ),
                          h(
                            'button',
                            {
                              type: 'button',
                              onClick: () => remove(row.id),
                              class: 'rounded-md border border-red-200 px-2 py-1 text-xs font-medium text-red-700',
                            },
                            'Xoá',
                          ),
                        ]),
                      ]),
                    ),
                  ),
                ]),
              ]),
      ])
  },
})
