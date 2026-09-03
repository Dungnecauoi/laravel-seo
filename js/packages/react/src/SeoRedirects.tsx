import { useEffect, useState } from 'react'
import { SeoApiError } from '@duxbo/seo-core'
import type { RedirectEntry, RedirectInput, RedirectMatchType, RedirectStatus, SeoClient } from '@duxbo/seo-core'

export interface SeoRedirectsProps {
  client: SeoClient
  className?: string
}

const STATUSES: { value: RedirectStatus; label: string }[] = [
  { value: 301, label: '301 — Chuyển vĩnh viễn' },
  { value: 302, label: '302 — Tạm thời' },
  { value: 307, label: '307 — Tạm thời (giữ method)' },
  { value: 308, label: '308 — Vĩnh viễn (giữ method)' },
  { value: 410, label: '410 — Đã gỡ bỏ' },
  { value: 451, label: '451 — Chặn theo pháp lý' },
]

const emptyForm: RedirectInput = { source: '', target: '', type: 'exact', status: 301, locale: '', notes: '' }

/**
 * Create, toggle, and delete redirect rules. `RedirectRepository::create()`
 * upserts on the source path, so resubmitting an existing source with a new
 * target edits it — no separate edit form.
 */
export function SeoRedirects({ client, className = '' }: SeoRedirectsProps) {
  const [entries, setEntries] = useState<RedirectEntry[] | null>(null)
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [form, setForm] = useState<RedirectInput>(emptyForm)
  const [fieldError, setFieldError] = useState<string | null>(null)
  const [isSaving, setIsSaving] = useState(false)

  function reload() {
    client.redirects(page).then((response) => {
      setEntries(response.data)
      setLastPage(response.meta.lastPage)
    })
  }

  useEffect(reload, [client, page])

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setFieldError(null)
    setIsSaving(true)

    try {
      await client.createRedirect({
        ...form,
        target: statusRedirects(form.status) ? (form.target ?? null) : null,
        locale: form.locale || null,
        notes: form.notes || null,
      })
      setForm(emptyForm)
      setPage(1)
      reload()
    } catch (e) {
      setFieldError(e instanceof SeoApiError ? e.fieldError('source') : e instanceof Error ? e.message : String(e))
    } finally {
      setIsSaving(false)
    }
  }

  async function toggle(id: number) {
    await client.toggleRedirect(id)
    reload()
  }

  async function remove(id: number) {
    if (!window.confirm('Xoá redirect này?')) return
    await client.deleteRedirect(id)
    reload()
  }

  return (
    <div className={`space-y-5 text-sm text-slate-900 ${className}`}>
      {fieldError && (
        <p className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-red-700" role="alert">
          {fieldError}
        </p>
      )}

      <form onSubmit={handleSubmit} className="space-y-3 rounded-md border border-slate-200 p-4">
        <h3 className="font-medium text-slate-900">Thêm / sửa redirect</h3>
        <p className="text-xs text-slate-500">Nhập lại đúng nguồn (source) đã có để sửa target.</p>

        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <Field label="Nguồn">
            <input
              required
              type="text"
              placeholder="/duong-dan-cu"
              value={form.source}
              onChange={(e) => setForm((f) => ({ ...f, source: e.target.value }))}
              className={inputClass}
            />
          </Field>
          <Field label="Đích">
            <input
              type="text"
              placeholder="/duong-dan-moi"
              value={form.target ?? ''}
              onChange={(e) => setForm((f) => ({ ...f, target: e.target.value }))}
              className={inputClass}
            />
          </Field>
        </div>

        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
          <Field label="Kiểu khớp">
            <select
              value={form.type}
              onChange={(e) => setForm((f) => ({ ...f, type: e.target.value as RedirectMatchType }))}
              className={inputClass}
            >
              <option value="exact">Chính xác</option>
              <option value="prefix">Tiền tố</option>
              <option value="regex">Regex</option>
            </select>
          </Field>
          <Field label="Mã trạng thái">
            <select
              value={form.status}
              onChange={(e) => setForm((f) => ({ ...f, status: Number(e.target.value) as RedirectStatus }))}
              className={inputClass}
            >
              {STATUSES.map((s) => (
                <option key={s.value} value={s.value}>
                  {s.label}
                </option>
              ))}
            </select>
          </Field>
          <Field label="Locale (tuỳ chọn)">
            <input
              type="text"
              placeholder="vi"
              value={form.locale ?? ''}
              onChange={(e) => setForm((f) => ({ ...f, locale: e.target.value }))}
              className={inputClass}
            />
          </Field>
        </div>

        <button
          type="submit"
          disabled={isSaving}
          className="rounded-md bg-slate-900 px-4 py-2 font-medium text-white disabled:cursor-not-allowed disabled:bg-slate-300"
        >
          {isSaving ? 'Đang lưu…' : 'Lưu'}
        </button>
      </form>

      {entries === null ? (
        <p className="text-slate-500" role="status">
          Đang tải…
        </p>
      ) : entries.length === 0 ? (
        <div className="rounded-md border border-dashed border-slate-300 p-6 text-center text-slate-400">
          Chưa có redirect nào.
        </div>
      ) : (
        <>
          <div className="overflow-x-auto rounded-md border border-slate-200">
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="border-b border-slate-100 text-xs uppercase text-slate-400">
                  <th className="px-3 py-2 font-medium">Nguồn</th>
                  <th className="px-3 py-2 font-medium">Đích</th>
                  <th className="px-3 py-2 font-medium">Mã</th>
                  <th className="px-3 py-2 font-medium">Lượt khớp</th>
                  <th className="px-3 py-2 font-medium">Trạng thái</th>
                  <th className="px-3 py-2" />
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {entries.map((r) => (
                  <tr key={r.id}>
                    <td className="px-3 py-2 font-mono text-xs">{r.source}</td>
                    <td className="px-3 py-2 font-mono text-xs">{r.target ?? '—'}</td>
                    <td className="px-3 py-2">{r.status}</td>
                    <td className="px-3 py-2">{r.hits.toLocaleString('vi-VN')}</td>
                    <td className="px-3 py-2">
                      {r.isActive ? (
                        <span className="rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700">
                          Đang bật
                        </span>
                      ) : (
                        <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">Tắt</span>
                      )}
                    </td>
                    <td className="whitespace-nowrap px-3 py-2 text-right">
                      <button
                        type="button"
                        onClick={() => toggle(r.id)}
                        className="mr-2 rounded-md border border-slate-300 px-2 py-1 text-xs font-medium text-slate-700"
                      >
                        {r.isActive ? 'Tắt' : 'Bật'}
                      </button>
                      <button
                        type="button"
                        onClick={() => remove(r.id)}
                        className="rounded-md border border-red-200 px-2 py-1 text-xs font-medium text-red-700"
                      >
                        Xoá
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {lastPage > 1 && (
            <div className="flex items-center gap-3 text-xs text-slate-500">
              <button
                type="button"
                disabled={page <= 1}
                onClick={() => setPage((p) => p - 1)}
                className="rounded-md border border-slate-300 px-2 py-1 font-medium text-slate-700 disabled:cursor-not-allowed disabled:opacity-40"
              >
                ← Trước
              </button>
              <span>
                Trang {page}/{lastPage}
              </span>
              <button
                type="button"
                disabled={page >= lastPage}
                onClick={() => setPage((p) => p + 1)}
                className="rounded-md border border-slate-300 px-2 py-1 font-medium text-slate-700 disabled:cursor-not-allowed disabled:opacity-40"
              >
                Sau →
              </button>
            </div>
          )}
        </>
      )}
    </div>
  )
}

function statusRedirects(status: RedirectStatus): boolean {
  return status !== 410 && status !== 451
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <label className="block">
      <span className="mb-1 block text-xs font-medium text-slate-700">{label}</span>
      {children}
    </label>
  )
}

const inputClass =
  'w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500'
