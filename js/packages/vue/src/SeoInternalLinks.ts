import { defineComponent, h, type PropType, ref, watch } from 'vue'
import type { InternalLinksResponse, SeoClient } from '@duxbo/seo-core'

/**
 * Read side of `php artisan seo:internal-links` — how many internal links
 * point at each record, flagging zero incoming as an orphan.
 */
export const SeoInternalLinks = defineComponent({
  name: 'SeoInternalLinks',

  props: {
    client: { type: Object as PropType<SeoClient>, required: true },
    type: { type: String, default: undefined },
  },

  setup(props) {
    const activeType = ref<string | undefined>(props.type)
    const response = ref<InternalLinksResponse | null>(null)

    function load() {
      props.client.internalLinks(activeType.value).then((data) => {
        response.value = data
        if (activeType.value === undefined && data.type) activeType.value = data.type
      })
    }

    watch(() => props.type, (t) => { activeType.value = t })
    watch(activeType, load, { immediate: true })

    return () => {
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
                  onClick: () => { activeType.value = t },
                  class:
                    t === r.type
                      ? 'rounded-md bg-slate-900 px-3 py-1 text-xs font-medium text-white'
                      : 'rounded-md border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700',
                },
                t,
              ),
            ),
          ),

        r.data.length === 0
          ? h('div', { class: 'rounded-md border border-dashed border-slate-300 p-6 text-center text-slate-400' }, [
              'Không có bản ghi nào. Chạy ',
              h('code', 'php artisan seo:internal-links'),
              ' để quét link.',
            ])
          : h('div', { class: 'overflow-x-auto rounded-md border border-slate-200' }, [
              h('table', { class: 'w-full text-left text-sm' }, [
                h('thead', [
                  h('tr', { class: 'border-b border-slate-100 text-xs uppercase text-slate-400' }, [
                    h('th', { class: 'px-3 py-2 font-medium' }, 'Trang'),
                    h('th', { class: 'px-3 py-2 font-medium' }, 'Đến'),
                    h('th', { class: 'px-3 py-2 font-medium' }, 'Đi'),
                    h('th', { class: 'px-3 py-2 font-medium' }, 'Trạng thái'),
                  ]),
                ]),
                h(
                  'tbody',
                  { class: 'divide-y divide-slate-100' },
                  r.data.map((row) =>
                    h('tr', { key: row.id }, [
                      h('td', { class: 'px-3 py-2 font-mono text-xs' }, row.url),
                      h('td', { class: 'px-3 py-2' }, row.incomingLinks),
                      h('td', { class: 'px-3 py-2' }, row.outgoingLinks),
                      h(
                        'td',
                        { class: 'px-3 py-2' },
                        row.isOrphan
                          ? h(
                              'span',
                              { class: 'rounded-full bg-red-50 px-2 py-0.5 text-xs text-red-700' },
                              'Mồ côi — không ai link tới',
                            )
                          : h('span', { class: 'rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700' }, 'OK'),
                      ),
                    ]),
                  ),
                ),
              ]),
            ]),
      ])
    }
  },
})
