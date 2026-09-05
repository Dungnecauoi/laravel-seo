import { defineComponent, h, type PropType, ref, watch } from 'vue'
import type { SeoClient, SearchConsoleStatsResponse } from '@duxbo/seo-core'

const WINDOWS = [7, 30, 90]

/**
 * Read side of `php artisan seo:search-console:sync` — clicks, impressions
 * and average position summed per page over the selected window.
 */
export const SeoSearchConsoleStats = defineComponent({
  name: 'SeoSearchConsoleStats',

  props: {
    client: { type: Object as PropType<SeoClient>, required: true },
  },

  setup(props) {
    const days = ref(30)
    const response = ref<SearchConsoleStatsResponse | null>(null)

    function load() {
      props.client.searchConsoleStats(days.value).then((data) => {
        response.value = data
      })
    }

    watch(days, load, { immediate: true })

    return () => {
      const r = response.value

      return h('div', { class: 'space-y-4 text-sm text-slate-900' }, [
        h('div', { class: 'flex flex-wrap items-center justify-between gap-3 rounded-md border border-slate-200 p-3' }, [
          h(
            'div',
            { class: 'flex gap-2' },
            WINDOWS.map((option) =>
              h(
                'button',
                {
                  key: option,
                  type: 'button',
                  onClick: () => { days.value = option },
                  class:
                    days.value === option
                      ? 'rounded-md bg-slate-900 px-3 py-1 text-xs font-medium text-white'
                      : 'rounded-md border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700',
                },
                `${option} ngày`,
              ),
            ),
          ),
          r &&
            h(
              'span',
              { class: 'text-xs text-slate-500' },
              `Tổng ${r.totalClicks.toLocaleString('vi-VN')} click, ${r.totalImpressions.toLocaleString('vi-VN')} impression trong ${r.days} ngày.`,
            ),
        ]),

        !r
          ? h('p', { class: 'text-slate-500', role: 'status' }, 'Đang tải…')
          : r.data.length === 0
            ? h('div', { class: 'rounded-md border border-dashed border-slate-300 p-6 text-center text-slate-400' }, [
                'Chưa có dữ liệu. Chạy ',
                h('code', 'php artisan seo:search-console:sync'),
                ' sau khi cấu hình Search Console.',
              ])
            : h('div', { class: 'overflow-x-auto rounded-md border border-slate-200' }, [
                h('table', { class: 'w-full text-left text-sm' }, [
                  h('thead', [
                    h('tr', { class: 'border-b border-slate-100 text-xs uppercase text-slate-400' }, [
                      h('th', { class: 'px-3 py-2 font-medium' }, 'Trang'),
                      h('th', { class: 'px-3 py-2 font-medium' }, 'Click'),
                      h('th', { class: 'px-3 py-2 font-medium' }, 'Impression'),
                      h('th', { class: 'px-3 py-2 font-medium' }, 'CTR'),
                      h('th', { class: 'px-3 py-2 font-medium' }, 'Vị trí TB'),
                    ]),
                  ]),
                  h(
                    'tbody',
                    { class: 'divide-y divide-slate-100' },
                    r.data.map((row) =>
                      h('tr', { key: row.url }, [
                        h('td', { class: 'px-3 py-2 font-mono text-xs' }, row.url),
                        h('td', { class: 'px-3 py-2' }, row.clicks.toLocaleString('vi-VN')),
                        h('td', { class: 'px-3 py-2' }, row.impressions.toLocaleString('vi-VN')),
                        h('td', { class: 'px-3 py-2' }, `${(row.ctr * 100).toFixed(1)}%`),
                        h('td', { class: 'px-3 py-2' }, row.position !== null ? row.position.toFixed(1) : '—'),
                      ]),
                    ),
                  ),
                ]),
              ]),
      ])
    }
  },
})
