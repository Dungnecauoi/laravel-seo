import { defineComponent, h, type PropType, ref } from 'vue'
import type { AuditHistoryResponse, SeoClient } from '@duxbo/seo-core'

/**
 * Read side of `php artisan seo:audit` — every batch it wrote, newest
 * first, so a score trend is visible without reading console output.
 */
export const SeoAuditHistory = defineComponent({
  name: 'SeoAuditHistory',

  props: {
    client: { type: Object as PropType<SeoClient>, required: true },
    /** Fully-qualified model class to filter by, e.g. `App\Models\Post`. */
    model: { type: String, default: undefined },
  },

  setup(props) {
    const response = ref<AuditHistoryResponse | null>(null)

    props.client.auditHistory(props.model).then((data) => {
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
          ['Chưa có lần audit nào. Chạy ', h('code', 'php artisan seo:audit'), ' để bắt đầu.'],
        )
      }

      return h('div', { class: 'overflow-x-auto rounded-md border border-slate-200 text-sm' }, [
        h('table', { class: 'w-full text-left' }, [
          h('thead', [
            h('tr', { class: 'border-b border-slate-100 text-xs uppercase text-slate-400' }, [
              h('th', { class: 'px-3 py-2 font-medium' }, 'Model'),
              h('th', { class: 'px-3 py-2 font-medium' }, 'Số bản ghi'),
              h('th', { class: 'px-3 py-2 font-medium' }, 'Điểm TB'),
              h('th', { class: 'px-3 py-2 font-medium' }, 'Thấp / Cao'),
              h('th', { class: 'px-3 py-2 font-medium' }, 'Chạy lúc'),
            ]),
          ]),
          h(
            'tbody',
            { class: 'divide-y divide-slate-100' },
            r.data.map((batch) =>
              h('tr', { key: batch.id }, [
                h('td', { class: 'px-3 py-2 font-mono text-xs' }, batch.model.split('\\').pop()),
                h('td', { class: 'px-3 py-2' }, batch.totalRecords.toLocaleString('vi-VN')),
                h(
                  'td',
                  { class: 'px-3 py-2' },
                  batch.averageScore !== null
                    ? h(
                        'span',
                        {
                          class:
                            batch.averageScore >= 80
                              ? 'rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700'
                              : batch.averageScore >= 50
                                ? 'rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-700'
                                : 'rounded-full bg-red-50 px-2 py-0.5 text-xs text-red-700',
                        },
                        batch.averageScore.toFixed(1),
                      )
                    : h('span', { class: 'text-slate-400' }, '—'),
                ),
                h(
                  'td',
                  { class: 'px-3 py-2 text-slate-500' },
                  `${batch.minScore ?? '—'} / ${batch.maxScore ?? '—'}`,
                ),
                h(
                  'td',
                  { class: 'px-3 py-2 text-slate-500' },
                  batch.startedAt ? new Date(batch.startedAt).toLocaleString('vi-VN') : '—',
                ),
              ]),
            ),
          ),
        ]),
      ])
    }
  },
})
