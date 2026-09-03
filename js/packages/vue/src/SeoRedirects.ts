import { defineComponent, h, type PropType, reactive, ref } from 'vue'
import { SeoApiError } from '@duxbo/seo-core'
import type { RedirectEntry, RedirectInput, RedirectMatchType, RedirectStatus, SeoClient } from '@duxbo/seo-core'

const STATUSES: { value: RedirectStatus; label: string }[] = [
  { value: 301, label: '301 — Chuyển vĩnh viễn' },
  { value: 302, label: '302 — Tạm thời' },
  { value: 307, label: '307 — Tạm thời (giữ method)' },
  { value: 308, label: '308 — Vĩnh viễn (giữ method)' },
  { value: 410, label: '410 — Đã gỡ bỏ' },
  { value: 451, label: '451 — Chặn theo pháp lý' },
]

const inputClass =
  'w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500'

/**
 * Create, toggle, and delete redirect rules. `RedirectRepository::create()`
 * upserts on the source path, so resubmitting an existing source with a new
 * target edits it — no separate edit form.
 */
export const SeoRedirects = defineComponent({
  name: 'SeoRedirects',

  props: {
    client: { type: Object as PropType<SeoClient>, required: true },
  },

  setup(props) {
    const entries = ref<RedirectEntry[] | null>(null)
    const page = ref(1)
    const lastPage = ref(1)
    const form = reactive<RedirectInput>({ source: '', target: '', type: 'exact', status: 301, locale: '', notes: '' })
    const fieldError = ref<string | null>(null)
    const isSaving = ref(false)

    function reload() {
      props.client.redirects(page.value).then((response) => {
        entries.value = response.data
        lastPage.value = response.meta.lastPage
      })
    }

    reload()

    async function handleSubmit() {
      fieldError.value = null
      isSaving.value = true

      try {
        await props.client.createRedirect({
          ...form,
          target: statusRedirects(form.status) ? (form.target ?? null) : null,
          locale: form.locale || null,
          notes: form.notes || null,
        })
        Object.assign(form, { source: '', target: '', type: 'exact', status: 301, locale: '', notes: '' })
        page.value = 1
        reload()
      } catch (e) {
        fieldError.value = e instanceof SeoApiError ? e.fieldError('source') : e instanceof Error ? e.message : String(e)
      } finally {
        isSaving.value = false
      }
    }

    async function toggle(id: number) {
      await props.client.toggleRedirect(id)
      reload()
    }

    async function remove(id: number) {
      if (!window.confirm('Xoá redirect này?')) return
      await props.client.deleteRedirect(id)
      reload()
    }

    return () =>
      h('div', { class: 'space-y-5 text-sm text-slate-900' }, [
        fieldError.value &&
          h('p', { class: 'rounded-md border border-red-200 bg-red-50 px-3 py-2 text-red-700', role: 'alert' }, fieldError.value),

        h(
          'form',
          {
            onSubmit: (e: Event) => {
              e.preventDefault()
              void handleSubmit()
            },
            class: 'space-y-3 rounded-md border border-slate-200 p-4',
          },
          [
            h('h3', { class: 'font-medium text-slate-900' }, 'Thêm / sửa redirect'),
            h('p', { class: 'text-xs text-slate-500' }, 'Nhập lại đúng nguồn (source) đã có để sửa target.'),

            h('div', { class: 'grid grid-cols-1 gap-3 sm:grid-cols-2' }, [
              field('Nguồn', h('input', {
                required: true,
                type: 'text',
                placeholder: '/duong-dan-cu',
                value: form.source,
                onInput: (e: Event) => { form.source = (e.target as HTMLInputElement).value },
                class: inputClass,
              })),
              field('Đích', h('input', {
                type: 'text',
                placeholder: '/duong-dan-moi',
                value: form.target ?? '',
                onInput: (e: Event) => { form.target = (e.target as HTMLInputElement).value },
                class: inputClass,
              })),
            ]),

            h('div', { class: 'grid grid-cols-1 gap-3 sm:grid-cols-3' }, [
              field('Kiểu khớp', h(
                'select',
                {
                  value: form.type,
                  onChange: (e: Event) => { form.type = (e.target as HTMLSelectElement).value as RedirectMatchType },
                  class: inputClass,
                },
                [
                  h('option', { value: 'exact' }, 'Chính xác'),
                  h('option', { value: 'prefix' }, 'Tiền tố'),
                  h('option', { value: 'regex' }, 'Regex'),
                ],
              )),
              field('Mã trạng thái', h(
                'select',
                {
                  value: form.status,
                  onChange: (e: Event) => { form.status = Number((e.target as HTMLSelectElement).value) as RedirectStatus },
                  class: inputClass,
                },
                STATUSES.map((s) => h('option', { value: s.value }, s.label)),
              )),
              field('Locale (tuỳ chọn)', h('input', {
                type: 'text',
                placeholder: 'vi',
                value: form.locale ?? '',
                onInput: (e: Event) => { form.locale = (e.target as HTMLInputElement).value },
                class: inputClass,
              })),
            ]),

            h(
              'button',
              {
                type: 'submit',
                disabled: isSaving.value,
                class: 'rounded-md bg-slate-900 px-4 py-2 font-medium text-white disabled:cursor-not-allowed disabled:bg-slate-300',
              },
              isSaving.value ? 'Đang lưu…' : 'Lưu',
            ),
          ],
        ),

        entries.value === null
          ? h('p', { class: 'text-slate-500', role: 'status' }, 'Đang tải…')
          : entries.value.length === 0
            ? h(
                'div',
                { class: 'rounded-md border border-dashed border-slate-300 p-6 text-center text-slate-400' },
                'Chưa có redirect nào.',
              )
            : h('div', {}, [
                h('div', { class: 'overflow-x-auto rounded-md border border-slate-200' }, [
                  h('table', { class: 'w-full text-left text-sm' }, [
                    h('thead', [
                      h('tr', { class: 'border-b border-slate-100 text-xs uppercase text-slate-400' }, [
                        h('th', { class: 'px-3 py-2 font-medium' }, 'Nguồn'),
                        h('th', { class: 'px-3 py-2 font-medium' }, 'Đích'),
                        h('th', { class: 'px-3 py-2 font-medium' }, 'Mã'),
                        h('th', { class: 'px-3 py-2 font-medium' }, 'Lượt khớp'),
                        h('th', { class: 'px-3 py-2 font-medium' }, 'Trạng thái'),
                        h('th', { class: 'px-3 py-2' }),
                      ]),
                    ]),
                    h(
                      'tbody',
                      { class: 'divide-y divide-slate-100' },
                      entries.value.map((r) =>
                        h('tr', { key: r.id }, [
                          h('td', { class: 'px-3 py-2 font-mono text-xs' }, r.source),
                          h('td', { class: 'px-3 py-2 font-mono text-xs' }, r.target ?? '—'),
                          h('td', { class: 'px-3 py-2' }, r.status),
                          h('td', { class: 'px-3 py-2' }, r.hits.toLocaleString('vi-VN')),
                          h(
                            'td',
                            { class: 'px-3 py-2' },
                            r.isActive
                              ? h('span', { class: 'rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700' }, 'Đang bật')
                              : h('span', { class: 'rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600' }, 'Tắt'),
                          ),
                          h('td', { class: 'whitespace-nowrap px-3 py-2 text-right' }, [
                            h(
                              'button',
                              {
                                type: 'button',
                                onClick: () => toggle(r.id),
                                class: 'mr-2 rounded-md border border-slate-300 px-2 py-1 text-xs font-medium text-slate-700',
                              },
                              r.isActive ? 'Tắt' : 'Bật',
                            ),
                            h(
                              'button',
                              {
                                type: 'button',
                                onClick: () => remove(r.id),
                                class: 'rounded-md border border-red-200 px-2 py-1 text-xs font-medium text-red-700',
                              },
                              'Xoá',
                            ),
                          ]),
                        ]),
                      ),
                    ),
                  ]),
                ]),

                lastPage.value > 1 &&
                  h('div', { class: 'flex items-center gap-3 text-xs text-slate-500' }, [
                    h(
                      'button',
                      {
                        type: 'button',
                        disabled: page.value <= 1,
                        onClick: () => page.value--,
                        class:
                          'rounded-md border border-slate-300 px-2 py-1 font-medium text-slate-700 disabled:cursor-not-allowed disabled:opacity-40',
                      },
                      '← Trước',
                    ),
                    h('span', `Trang ${page.value}/${lastPage.value}`),
                    h(
                      'button',
                      {
                        type: 'button',
                        disabled: page.value >= lastPage.value,
                        onClick: () => page.value++,
                        class:
                          'rounded-md border border-slate-300 px-2 py-1 font-medium text-slate-700 disabled:cursor-not-allowed disabled:opacity-40',
                      },
                      'Sau →',
                    ),
                  ]),
              ]),
      ])
  },
})

function statusRedirects(status: RedirectStatus): boolean {
  return status !== 410 && status !== 451
}

function field(label: string, input: ReturnType<typeof h>) {
  return h('label', { class: 'block' }, [h('span', { class: 'mb-1 block text-xs font-medium text-slate-700' }, label), input])
}
