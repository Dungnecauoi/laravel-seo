import { useEffect, useState } from 'react'
import type { DashboardStats, SeoClient } from '@duxbo/seo-core'

export interface SeoDashboardProps {
  client: SeoClient
  /** Called when the user picks a content type from the missing-meta table. */
  onSelectType?: (type: string) => void
  className?: string
}

/**
 * Stats a `php artisan seo:duplicates` run and the database console would
 * otherwise be the only way to see: records with SEO data, active redirects,
 * 404 count, and which content types still lean on the default template.
 */
export function SeoDashboard({ client, onSelectType, className = '' }: SeoDashboardProps) {
  const [stats, setStats] = useState<DashboardStats | null>(null)
  const [error, setError] = useState<Error | null>(null)

  useEffect(() => {
    let cancelled = false

    client
      .dashboard()
      .then((data) => {
        if (!cancelled) setStats(data)
      })
      .catch((e: unknown) => {
        if (!cancelled) setError(e instanceof Error ? e : new Error(String(e)))
      })

    return () => {
      cancelled = true
    }
  }, [client])

  if (error) {
    return (
      <p className={`rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 ${className}`} role="alert">
        {error.message}
      </p>
    )
  }

  if (!stats) {
    return (
      <p className={`text-sm text-slate-500 ${className}`} role="status">
        Đang tải…
      </p>
    )
  }

  return (
    <div className={`space-y-5 text-sm text-slate-900 ${className}`}>
      {!stats.seoEnabled && (
        <p className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-red-700" role="alert">
          <strong>SEO đang tắt toàn site</strong> (<code>SEO_ENABLED=false</code>). Mọi trang đang bị{' '}
          <code>noindex, nofollow</code>, sitemap trống. Đúng cho domain demo — nhớ bật lại trước khi lên production
          thật.
        </p>
      )}

      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        <Stat value={stats.totalRecords} label="Bản ghi có SEO" />
        <Stat value={stats.totalMissing} label="Chưa có meta riêng" warn={stats.totalMissing > 0} />
        <Stat value={stats.activeRedirects} label="Redirect đang bật" />
        <Stat value={stats.notFoundCount} label="Link 404 ghi nhận" warn={stats.notFoundCount > 0} />
        <Stat value={stats.sitemapSources} label="Nguồn sitemap" />
      </div>

      {stats.exposedTypes.length === 0 ? (
        <div className="rounded-md border border-slate-200 p-4 text-slate-500">
          Chưa có model nào được expose. Thêm vào <code>seo.api.models</code> trong <code>config/seo.php</code> để
          bảng nội dung và các thao tác của panel có thể truy cập.
        </div>
      ) : (
        <div className="rounded-md border border-slate-200 p-4">
          <h3 className="mb-3 font-medium text-slate-900">Theo loại nội dung</h3>
          <table className="w-full text-left text-sm">
            <thead>
              <tr className="text-xs uppercase text-slate-400">
                <th className="pb-2 font-medium">Loại</th>
                <th className="pb-2 font-medium">Chưa có meta riêng</th>
                <th className="pb-2" />
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {Object.entries(stats.missingByType).map(([type, count]) => (
                <tr key={type}>
                  <td className="py-2 font-mono text-xs">{type}</td>
                  <td className="py-2">
                    {count > 0 ? (
                      <span className="rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-700">{count}</span>
                    ) : (
                      <span className="rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700">Đủ</span>
                    )}
                  </td>
                  <td className="py-2 text-right">
                    {onSelectType && (
                      <button
                        type="button"
                        onClick={() => onSelectType(type)}
                        className="text-xs text-slate-500 underline hover:text-slate-800"
                      >
                        Xem danh sách →
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <div className="rounded-md border border-slate-200 p-4">
        <h3 className="mb-2 font-medium text-slate-900">Kiểm tra trùng lặp</h3>
        <p className="text-slate-500">
          Trang này chỉ cảnh báo trùng title/description ngay lúc lưu. Quét toàn site (bắt cả trường hợp trùng qua
          template mặc định) bằng:
        </p>
        <pre className="mt-2 overflow-x-auto rounded border border-slate-200 bg-slate-50 px-3 py-2 text-xs">
          php artisan seo:duplicates {'{App\\Models\\Post}'} --field=both
        </pre>
      </div>
    </div>
  )
}

function Stat({ value, label, warn = false }: { value: number; label: string; warn?: boolean }) {
  return (
    <div className="rounded-md border border-slate-200 p-3">
      <div className={`text-2xl font-semibold ${warn ? 'text-amber-600' : 'text-slate-900'}`}>
        {value.toLocaleString('vi-VN')}
      </div>
      <div className="mt-1 text-xs text-slate-500">{label}</div>
    </div>
  )
}
