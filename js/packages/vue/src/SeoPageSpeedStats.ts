import { defineComponent, h, type PropType, ref, watch } from 'vue'
import type { PageSpeedStatsResponse, SeoClient } from '@duxbo/seo-core'

/**
 * The latest PageSpeed Insights run per URL — performance score and Core
 * Web Vitals, from whichever URLs `php artisan seo:pagespeed` was last
 * pointed at. `fieldDataAvailable: false` renders as "not enough data" for
 * a low-traffic URL, not as an error.
 */
export const SeoPageSpeedStats = defineComponent({
  name: 'SeoPageSpeedStats',

  props: {
    client: { type: Object as PropType<SeoClient>, required: true },
    strategy: { type: String as PropType<'mobile' | 'desktop'>, default: 'mobile' },
  },

  setup(props) {
    const response = ref<PageSpeedStatsResponse | null>(null)

    function load(): void {
      response.value = null
      props.client.pageSpeedStats(props.strategy).then((data) => {
        response.value = data
      })
    }

    load()
    watch(() => props.strategy, load)

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
            h('code', 'php artisan seo:pagespeed https://vidu.vn/trang'),
            ' sau khi cấu hình API key trong ',
            h('code', 'seo.pagespeed'),
            '.',
          ],
        )
      }

      return h('div', { class: 'overflow-x-auto rounded-md border border-slate-200 text-sm' }, [
        h('table', { class: 'w-full text-left' }, [
          h('thead', [
            h('tr', { class: 'border-b border-slate-100 text-xs uppercase text-slate-400' }, [
              h('th', { class: 'px-3 py-2 font-medium' }, 'Trang'),
              h('th', { class: 'px-3 py-2 font-medium' }, 'Điểm'),
              h('th', { class: 'px-3 py-2 font-medium' }, 'LCP'),
              h('th', { class: 'px-3 py-2 font-medium' }, 'CLS'),
              h('th', { class: 'px-3 py-2 font-medium' }, 'CWV (thực tế)'),
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
                  row.performanceScore === null
                    ? '—'
                    : h(
                        'span',
                        {
                          class: `rounded-full px-2 py-0.5 text-xs ${
                            row.performanceScore >= 90
                              ? 'bg-emerald-50 text-emerald-700'
                              : row.performanceScore >= 50
                                ? 'bg-amber-50 text-amber-700'
                                : 'bg-red-50 text-red-700'
                          }`,
                        },
                        `${row.performanceScore}/100`,
                      ),
                ),
                h('td', { class: 'px-3 py-2' }, row.lcpMs === null ? '—' : `${(row.lcpMs / 1000).toFixed(1)}s`),
                h('td', { class: 'px-3 py-2' }, row.clsScore === null ? '—' : row.clsScore.toFixed(3)),
                h(
                  'td',
                  { class: 'px-3 py-2 text-slate-500' },
                  row.fieldDataAvailable ? (row.cwvCategory ?? '—') : 'Chưa đủ dữ liệu',
                ),
              ]),
            ),
          ),
        ]),
      ])
    }
  },
})
