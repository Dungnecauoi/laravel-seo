import { defineComponent, h, type PropType, ref } from 'vue'
import type { DashboardStats, SeoClient } from '@duxbo/seo-core'

/**
 * Stats a `php artisan seo:duplicates` run and the database console would
 * otherwise be the only way to see: records with SEO data, active redirects,
 * 404 count, and which content types still lean on the default template.
 */
export const SeoDashboard = defineComponent({
  name: 'SeoDashboard',

  props: {
    client: { type: Object as PropType<SeoClient>, required: true },
  },

  emits: ['selectType'],

  setup(props, { emit }) {
    const stats = ref<DashboardStats | null>(null)
    const error = ref<Error | null>(null)

    props.client
      .dashboard()
      .then((data) => {
        stats.value = data
      })
      .catch((e: unknown) => {
        error.value = e instanceof Error ? e : new Error(String(e))
      })

    return () => {
      if (error.value) {
        return h(
          'p',
          { class: 'rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700', role: 'alert' },
          error.value.message,
        )
      }

      if (!stats.value) {
        return h('p', { class: 'text-sm text-slate-500', role: 'status' }, 'Đang tải…')
      }

      const s = stats.value

      return h('div', { class: 'space-y-5 text-sm text-slate-900' }, [
        !s.seoEnabled &&
          h(
            'p',
            { class: 'rounded-md border border-red-200 bg-red-50 px-3 py-2 text-red-700', role: 'alert' },
            [
              h('strong', 'SEO đang tắt toàn site'),
              ' (',
              h('code', 'SEO_ENABLED=false'),
              '). Mọi trang đang bị ',
              h('code', 'noindex, nofollow'),
              ', sitemap trống. Đúng cho domain demo — nhớ bật lại trước khi lên production thật.',
            ],
          ),

        h('div', { class: 'grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5' }, [
          stat(s.totalRecords, 'Bản ghi có SEO'),
          stat(s.totalMissing, 'Chưa có meta riêng', s.totalMissing > 0),
          stat(s.activeRedirects, 'Redirect đang bật'),
          stat(s.notFoundCount, 'Link 404 ghi nhận', s.notFoundCount > 0),
          stat(s.sitemapSources, 'Nguồn sitemap'),
        ]),

        s.exposedTypes.length === 0
          ? h('div', { class: 'rounded-md border border-slate-200 p-4 text-slate-500' }, [
              'Chưa có model nào được expose. Thêm vào ',
              h('code', 'seo.api.models'),
              ' trong ',
              h('code', 'config/seo.php'),
              ' để bảng nội dung và các thao tác của panel có thể truy cập.',
            ])
          : h('div', { class: 'rounded-md border border-slate-200 p-4' }, [
              h('h3', { class: 'mb-3 font-medium text-slate-900' }, 'Theo loại nội dung'),
              h('table', { class: 'w-full text-left text-sm' }, [
                h('thead', [
                  h('tr', { class: 'text-xs uppercase text-slate-400' }, [
                    h('th', { class: 'pb-2 font-medium' }, 'Loại'),
                    h('th', { class: 'pb-2 font-medium' }, 'Chưa có meta riêng'),
                    h('th', { class: 'pb-2' }),
                  ]),
                ]),
                h(
                  'tbody',
                  { class: 'divide-y divide-slate-100' },
                  Object.entries(s.missingByType).map(([type, count]) =>
                    h('tr', { key: type }, [
                      h('td', { class: 'py-2 font-mono text-xs' }, type),
                      h(
                        'td',
                        { class: 'py-2' },
                        count > 0
                          ? h('span', { class: 'rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-700' }, count)
                          : h('span', { class: 'rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700' }, 'Đủ'),
                      ),
                      h(
                        'td',
                        { class: 'py-2 text-right' },
                        h(
                          'button',
                          {
                            type: 'button',
                            onClick: () => emit('selectType', type),
                            class: 'text-xs text-slate-500 underline hover:text-slate-800',
                          },
                          'Xem danh sách →',
                        ),
                      ),
                    ]),
                  ),
                ),
              ]),
            ]),

        h('div', { class: 'rounded-md border border-slate-200 p-4' }, [
          h('h3', { class: 'mb-2 font-medium text-slate-900' }, 'Kiểm tra trùng lặp'),
          h(
            'p',
            { class: 'text-slate-500' },
            'Trang này chỉ cảnh báo trùng title/description ngay lúc lưu. Quét toàn site (bắt cả trường hợp trùng qua template mặc định) bằng:',
          ),
          h(
            'pre',
            { class: 'mt-2 overflow-x-auto rounded border border-slate-200 bg-slate-50 px-3 py-2 text-xs' },
            'php artisan seo:duplicates {App\\Models\\Post} --field=both',
          ),
        ]),
      ])
    }
  },
})

function stat(value: number, label: string, warn = false) {
  return h('div', { class: 'rounded-md border border-slate-200 p-3' }, [
    h('div', { class: `text-2xl font-semibold ${warn ? 'text-amber-600' : 'text-slate-900'}` }, value.toLocaleString('vi-VN')),
    h('div', { class: 'mt-1 text-xs text-slate-500' }, label),
  ])
}
