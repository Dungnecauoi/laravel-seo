import { useEffect, useState } from 'react'
import type { IndexNowLogResponse, SeoClient } from '@duxbo/seo-core'

export interface SeoIndexNowLogProps {
  client: SeoClient
  className?: string
}

/**
 * Recent IndexNow submissions — one row per API call, not per URL, matching
 * how {@see IndexNowSubmitter} logs it server-side.
 */
export function SeoIndexNowLog({ client, className = '' }: SeoIndexNowLogProps) {
  const [response, setResponse] = useState<IndexNowLogResponse | null>(null)

  useEffect(() => {
    client.indexNowLog().then(setResponse)
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
        Chưa có lần gửi nào. Chạy <code>php artisan seo:indexnow</code> sau khi bật IndexNow trong cấu hình.
      </div>
    )
  }

  return (
    <div className={`overflow-x-auto rounded-md border border-slate-200 text-sm ${className}`}>
      <table className="w-full text-left">
        <thead>
          <tr className="border-b border-slate-100 text-xs uppercase text-slate-400">
            <th className="px-3 py-2 font-medium">URL</th>
            <th className="px-3 py-2 font-medium">Số lượng</th>
            <th className="px-3 py-2 font-medium">Trạng thái</th>
            <th className="px-3 py-2 font-medium">Thời gian</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100">
          {response.data.map((entry) => (
            <tr key={entry.id}>
              <td className="max-w-xs truncate px-3 py-2 font-mono text-xs" title={entry.urls.join(', ')}>
                {entry.urls.slice(0, 2).join(', ')}
                {entry.urls.length > 2 && (
                  <span className="text-slate-400">{` +${entry.urls.length - 2} nữa`}</span>
                )}
              </td>
              <td className="px-3 py-2">{entry.urlCount}</td>
              <td className="px-3 py-2">
                {entry.successful ? (
                  <span className="rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700">
                    {`Thành công${entry.statusCode ? ` (${entry.statusCode})` : ''}`}
                  </span>
                ) : (
                  <span
                    className="rounded-full bg-red-50 px-2 py-0.5 text-xs text-red-700"
                    title={entry.error ?? undefined}
                  >
                    {`Lỗi${entry.statusCode ? ` (${entry.statusCode})` : ''}`}
                  </span>
                )}
              </td>
              <td className="px-3 py-2 text-slate-500">
                {entry.createdAt ? new Date(entry.createdAt).toLocaleString('vi-VN') : '—'}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
