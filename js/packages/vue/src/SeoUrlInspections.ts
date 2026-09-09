import { defineComponent, h, type PropType, ref } from 'vue'
import type { SeoClient, UrlInspectionsResponse } from '@duxbo/seo-core'

/**
 * The latest URL Inspection result per URL — whether Google has it indexed,
 * and why not if it doesn't, from whichever URLs `php artisan
 * seo:search-console:inspect` was last pointed at.
 */
export const SeoUrlInspections = defineComponent({
  name: 'SeoUrlInspections',

  props: {
    client: { type: Object as PropType<SeoClient>, required: true },
  },

  setup(props) {
    const response = ref<UrlInspectionsResponse | null>(null)

    props.client.urlInspections().then((data) => {
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
            'Chưa có dữ liệu. Chạy ',
            h('code', 'php artisan seo:search-console:inspect https://vidu.vn/trang'),
            ' sau khi cấu hình Search Console.',
          ],
        )
      }

      return h('div', { class: 'overflow-x-auto rounded-md border border-slate-200 text-sm' }, [
        h('table', { class: 'w-full text-left' }, [
          h('thead', [
            h('tr', { class: 'border-b border-slate-100 text-xs uppercase text-slate-400' }, [
              h('th', { class: 'px-3 py-2 font-medium' }, 'Trang'),
              h('th', { class: 'px-3 py-2 font-medium' }, 'Trạng thái'),
              h('th', { class: 'px-3 py-2 font-medium' }, 'Verdict'),
              h('th', { class: 'px-3 py-2 font-medium' }, 'Mobile'),
              h('th', { class: 'px-3 py-2 font-medium' }, 'Rich results'),
            ]),
          ]),
          h(
            'tbody',
            { class: 'divide-y divide-slate-100' },
            r.data.map((row) =>
              h('tr', { key: row.url }, [
                h('td', { class: 'max-w-xs truncate px-3 py-2 font-mono text-xs', title: row.url }, row.url),
                h('td', { class: 'px-3 py-2' }, row.coverageState ?? '—'),
                h(
                  'td',
                  { class: 'px-3 py-2' },
                  row.verdict === 'PASS'
                    ? h('span', { class: 'rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700' }, 'PASS')
                    : row.verdict === 'FAIL'
                      ? h('span', { class: 'rounded-full bg-red-50 px-2 py-0.5 text-xs text-red-700' }, 'FAIL')
                      : h('span', { class: 'text-slate-400' }, row.verdict ?? '—'),
                ),
                h(
                  'td',
                  { class: 'px-3 py-2 text-slate-500', title: row.mobileUsabilityIssues.join(', ') },
                  `${row.mobileUsabilityVerdict ?? '—'}${
                    row.mobileUsabilityIssues.length > 0 ? ` (${row.mobileUsabilityIssues.length} lỗi)` : ''
                  }`,
                ),
                h('td', { class: 'px-3 py-2 text-slate-500' }, row.richResultsVerdict ?? '—'),
              ]),
            ),
          ),
        ]),
      ])
    }
  },
})
