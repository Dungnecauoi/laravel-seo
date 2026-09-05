import { defineComponent, h, type PropType, ref } from 'vue'
import type { IndexNowLogResponse, SeoClient } from '@duxbo/seo-core'

/**
 * Recent IndexNow submissions — one row per API call, not per URL, matching
 * how `IndexNowSubmitter` logs it server-side.
 */
export const SeoIndexNowLog = defineComponent({
  name: 'SeoIndexNowLog',

  props: {
    client: { type: Object as PropType<SeoClient>, required: true },
  },

  setup(props) {
    const response = ref<IndexNowLogResponse | null>(null)

    props.client.indexNowLog().then((data) => {
      response.value = data
    })

    return () => {
      const r = response.value

      if (!r) {
        return h('p', { class: 'text-sm text-slate-500', role: 'status' }, 'Đang tải…')
      }

      if (r.data.length === 0) {
        return h(
          'div',
          { class: 'rounded-md border border-dashed border-slate-300 p-6 text-center text-slate-400' },
          ['Chưa có lần gửi nào. Chạy ', h('code', 'php artisan seo:indexnow'), ' sau khi bật IndexNow trong cấu hình.'],
        )
      }

      return h('div', { class: 'overflow-x-auto rounded-md border border-slate-200 text-sm' }, [
        h('table', { class: 'w-full text-left' }, [
          h('thead', [
            h('tr', { class: 'border-b border-slate-100 text-xs uppercase text-slate-400' }, [
              h('th', { class: 'px-3 py-2 font-medium' }, 'URL'),
              h('th', { class: 'px-3 py-2 font-medium' }, 'Số lượng'),
              h('th', { class: 'px-3 py-2 font-medium' }, 'Trạng thái'),
              h('th', { class: 'px-3 py-2 font-medium' }, 'Thời gian'),
            ]),
          ]),
          h(
            'tbody',
            { class: 'divide-y divide-slate-100' },
            r.data.map((entry) =>
              h('tr', { key: entry.id }, [
                h(
                  'td',
                  { class: 'max-w-xs truncate px-3 py-2 font-mono text-xs', title: entry.urls.join(', ') },
                  [
                    entry.urls.slice(0, 2).join(', '),
                    entry.urls.length > 2 && h('span', { class: 'text-slate-400' }, ` +${entry.urls.length - 2} nữa`),
                  ],
                ),
                h('td', { class: 'px-3 py-2' }, entry.urlCount),
                h(
                  'td',
                  { class: 'px-3 py-2' },
                  entry.successful
                    ? h(
                        'span',
                        { class: 'rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700' },
                        `Thành công${entry.statusCode ? ` (${entry.statusCode})` : ''}`,
                      )
                    : h(
                        'span',
                        {
                          class: 'rounded-full bg-red-50 px-2 py-0.5 text-xs text-red-700',
                          title: entry.error ?? undefined,
                        },
                        `Lỗi${entry.statusCode ? ` (${entry.statusCode})` : ''}`,
                      ),
                ),
                h(
                  'td',
                  { class: 'px-3 py-2 text-slate-500' },
                  entry.createdAt ? new Date(entry.createdAt).toLocaleString('vi-VN') : '—',
                ),
              ]),
            ),
          ),
        ]),
      ])
    }
  },
})
