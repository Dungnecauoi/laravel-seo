import { useEffect, useState } from 'react'
import type { AiToolCallsResponse, SeoClient } from '@duxbo/seo-core'

export interface SeoAiToolCallsProps {
  client: SeoClient
  className?: string
}

const RISK_STYLE: Record<string, string> = {
  read: 'bg-slate-50 text-slate-600',
  write: 'bg-amber-50 text-amber-700',
  destructive: 'bg-red-50 text-red-700',
}

/**
 * Every AI tool call, propose and apply alike — what an AI agent (via the
 * REST manifest or MCP) has actually been doing on this site.
 */
export function SeoAiToolCalls({ client, className = '' }: SeoAiToolCallsProps) {
  const [response, setResponse] = useState<AiToolCallsResponse | null>(null)

  useEffect(() => {
    client.aiToolCalls().then(setResponse)
  }, [client])

  if (!response) {
    return (
      <p className={`text-sm text-slate-500 ${className}`} role="status">
        Đang tải…
      </p>
    )
  }

  if (response.data.length === 0) {
    return (
      <div className={`rounded-md border border-dashed border-slate-300 p-6 text-center text-slate-400 ${className}`}>
        Chưa có lần gọi tool AI nào. Gắn một AI agent qua REST (<code>/api/seo/v1/ai/tools</code>) hoặc MCP
        (<code>/api/seo/v1/mcp</code>) để bắt đầu.
      </div>
    )
  }

  return (
    <div className={`overflow-x-auto rounded-md border border-slate-200 text-sm ${className}`}>
      <table className="w-full text-left">
        <thead>
          <tr className="border-b border-slate-100 text-xs uppercase text-slate-400">
            <th className="px-3 py-2 font-medium">Tool</th>
            <th className="px-3 py-2 font-medium">Rủi ro</th>
            <th className="px-3 py-2 font-medium">Trạng thái</th>
            <th className="px-3 py-2 font-medium">Thời gian</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100">
          {response.data.map((call) => (
            <tr key={call.id}>
              <td className="px-3 py-2 font-mono text-xs">{call.tool}</td>
              <td className="px-3 py-2">
                <span className={`rounded-full px-2 py-0.5 text-xs ${RISK_STYLE[call.riskTier] ?? RISK_STYLE.read}`}>
                  {call.riskTier}
                </span>
              </td>
              <td className="px-3 py-2">
                {call.status === 'applied' ? (
                  <span className="rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700">Đã áp dụng</span>
                ) : (
                  <span className="rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-700">Đã đề xuất</span>
                )}
              </td>
              <td className="px-3 py-2 text-slate-500">
                {call.createdAt ? new Date(call.createdAt).toLocaleString('vi-VN') : '—'}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
