import { useEffect, useState } from 'react'
import type { SearchConsoleStatsResponse, SeoClient } from '@duxbo/seo-core'

export interface SeoSearchConsoleStatsProps {
  client: SeoClient
  className?: string
}

const WINDOWS = [7, 30, 90]

/**
 * Read side of `php artisan seo:search-console:sync` — clicks, impressions
 * and average position summed per page over the selected window.
 */
export function SeoSearchConsoleStats({ client, className = '' }: SeoSearchConsoleStatsProps) {
  const [days, setDays] = useState(30)
  const [response, setResponse] = useState<SearchConsoleStatsResponse | null>(null)

  useEffect(() => {
    client.searchConsoleStats(days).then(setResponse)
  }, [client, days])

  return (
    <div className={`space-y-4 text-sm text-slate-900 ${className}`}>
      <div className="flex flex-wrap items-center justify-between gap-3 rounded-md border border-slate-200 p-3">
        <div className="flex gap-2">
          {WINDOWS.map((option) => (
            <button
              key={option}
              type="button"
              onClick={() => setDays(option)}
              className={
                days === option
                  ? 'rounded-md bg-slate-900 px-3 py-1 text-xs font-medium text-white'
                  : 'rounded-md border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700'
              }
            >
              {`${option} ngày`}
            </button>
          ))}
        </div>
        {response && (
          <span className="text-xs text-slate-500">
            Tổng {response.totalClicks.toLocaleString('vi-VN')} click,{' '}
            {response.totalImpressions.toLocaleString('vi-VN')} impression trong {response.days} ngày.
          </span>
        )}
      </div>

      {!response ? (
        <p className="text-slate-500" role="status">
          Đang tải…
        </p>
      ) : response.data.length === 0 ? (
        <div className="rounded-md border border-dashed border-slate-300 p-6 text-center text-slate-400">
          Chưa có dữ liệu. Chạy <code>php artisan seo:search-console:sync</code> sau khi cấu hình Search Console.
        </div>
      ) : (
        <div className="overflow-x-auto rounded-md border border-slate-200">
          <table className="w-full text-left text-sm">
            <thead>
              <tr className="border-b border-slate-100 text-xs uppercase text-slate-400">
                <th className="px-3 py-2 font-medium">Trang</th>
                <th className="px-3 py-2 font-medium">Click</th>
                <th className="px-3 py-2 font-medium">Impression</th>
                <th className="px-3 py-2 font-medium">CTR</th>
                <th className="px-3 py-2 font-medium">Vị trí TB</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {response.data.map((row) => (
                <tr key={row.url}>
                  <td className="px-3 py-2 font-mono text-xs">{row.url}</td>
                  <td className="px-3 py-2">{row.clicks.toLocaleString('vi-VN')}</td>
                  <td className="px-3 py-2">{row.impressions.toLocaleString('vi-VN')}</td>
                  <td className="px-3 py-2">{`${(row.ctr * 100).toFixed(1)}%`}</td>
                  <td className="px-3 py-2">{row.position !== null ? row.position.toFixed(1) : '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}
