import { defineComponent, h, type PropType, ref } from 'vue'
import type { BrokenLinksResponse, SeoClient } from '@duxbo/seo-core'

/**
 * Currently-known-broken external URLs and which records cite them, from
 * whatever `php artisan seo:broken-links` last found.
 */
export const SeoBrokenLinks = defineComponent({
  name: 'SeoBrokenLinks',

  props: {
    client: { type: Object as PropType<SeoClient>, required: true },
  },

  setup(props) {
    const response = ref<BrokenLinksResponse | null>(null)

    props.client.brokenLinks().then((data) => {
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
          [
            'Không có link chết nào được ghi nhận. Chạy ',
            h('code', 'php artisan seo:broken-links App\\Models\\Post'),
            ' để quét.',
          ],
        )
      }

      return h('div', { class: 'overflow-x-auto rounded-md border border-slate-200 text-sm' }, [
        h('table', { class: 'w-full text-left' }, [
          h('thead', [
            h('tr', { class: 'border-b border-slate-100 text-xs uppercase text-slate-400' }, [
              h('th', { class: 'px-3 py-2 font-medium' }, 'URL'),
              h('th', { class: 'px-3 py-2 font-medium' }, 'Lỗi'),
              h('th', { class: 'px-3 py-2 font-medium' }, 'Số trang trỏ tới'),
            ]),
          ]),
          h(
            'tbody',
            { class: 'divide-y divide-slate-100' },
            r.data.map((row) =>
              h('tr', { key: row.url }, [
                h('td', { class: 'max-w-xs truncate px-3 py-2 font-mono text-xs', title: row.url }, row.url),
                h(
                  'td',
                  { class: 'px-3 py-2' },
                  h(
                    'span',
                    { class: 'rounded-full bg-red-50 px-2 py-0.5 text-xs text-red-700', title: row.error ?? undefined },
                    row.statusCode !== null ? `HTTP ${row.statusCode}` : (row.error ?? 'Lỗi'),
                  ),
                ),
                h(
                  'td',
                  {
                    class: 'px-3 py-2 text-slate-500',
                    title: row.sources.map((s) => `${s.sourceType}:${s.sourceId}`).join(', '),
                  },
                  String(row.sources.length),
                ),
              ]),
            ),
          ),
        ]),
      ])
    }
  },
})
