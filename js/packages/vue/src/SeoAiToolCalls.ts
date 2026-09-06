import { defineComponent, h, type PropType, ref } from 'vue'
import type { AiToolCallEntry, AiToolCallsResponse, SeoClient } from '@duxbo/seo-core'

const RISK_STYLE: Record<string, string> = {
  read: 'bg-slate-50 text-slate-600',
  write: 'bg-amber-50 text-amber-700',
  destructive: 'bg-red-50 text-red-700',
}

/**
 * Every AI tool call, propose and apply alike — what an AI agent (via the
 * REST manifest or MCP) has actually been doing on this site.
 */
export const SeoAiToolCalls = defineComponent({
  name: 'SeoAiToolCalls',

  props: {
    client: { type: Object as PropType<SeoClient>, required: true },
  },

  setup(props) {
    const response = ref<AiToolCallsResponse | null>(null)

    props.client.aiToolCalls().then((data) => {
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
            'Chưa có lần gọi tool AI nào. Gắn một AI agent qua REST (',
            h('code', '/api/seo/v1/ai/tools'),
            ') hoặc MCP (',
            h('code', '/api/seo/v1/mcp'),
            ') để bắt đầu.',
          ],
        )
      }

      return h('div', { class: 'overflow-x-auto rounded-md border border-slate-200 text-sm' }, [
        h('table', { class: 'w-full text-left' }, [
          h('thead', [
            h('tr', { class: 'border-b border-slate-100 text-xs uppercase text-slate-400' }, [
              h('th', { class: 'px-3 py-2 font-medium' }, 'Tool'),
              h('th', { class: 'px-3 py-2 font-medium' }, 'Rủi ro'),
              h('th', { class: 'px-3 py-2 font-medium' }, 'Trạng thái'),
              h('th', { class: 'px-3 py-2 font-medium' }, 'Thời gian'),
            ]),
          ]),
          h(
            'tbody',
            { class: 'divide-y divide-slate-100' },
            r.data.map((call: AiToolCallEntry) =>
              h('tr', { key: call.id }, [
                h('td', { class: 'px-3 py-2 font-mono text-xs' }, call.tool),
                h(
                  'td',
                  { class: 'px-3 py-2' },
                  h(
                    'span',
                    { class: `rounded-full px-2 py-0.5 text-xs ${RISK_STYLE[call.riskTier] ?? RISK_STYLE.read}` },
                    call.riskTier,
                  ),
                ),
                h(
                  'td',
                  { class: 'px-3 py-2' },
                  call.status === 'applied'
                    ? h('span', { class: 'rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700' }, 'Đã áp dụng')
                    : h('span', { class: 'rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-700' }, 'Đã đề xuất'),
                ),
                h(
                  'td',
                  { class: 'px-3 py-2 text-slate-500' },
                  call.createdAt ? new Date(call.createdAt).toLocaleString('vi-VN') : '—',
                ),
              ]),
            ),
          ),
        ]),
      ])
    }
  },
})
