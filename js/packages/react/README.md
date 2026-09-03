# @duxbo/seo-react

React hooks and a Tailwind-styled panel for [duxbo/laravel-seo](https://github.com/Dungnecauoi/laravel-seo),
built on `@duxbo/seo-core`.

```bash
npm install @duxbo/seo-react
```

```tsx
import { useSeoClient, SeoPanel } from '@duxbo/seo-react'

function EditPost({ post }: { post: { id: number; body: string } }) {
  const client = useSeoClient({ baseUrl: 'https://example.com', token })

  return (
    <SeoPanel
      client={client}
      target={{ type: 'post', id: post.id }}
      content={post.body}
      onSaved={() => toast('Đã lưu')}
    />
  )
}
```

Or use the hook directly for a custom layout:

```tsx
const store = useMetaStore(client, { type: 'post', id: post.id })
store.draft.title
store.isDirty
store.set('title', 'Tiêu đề mới')
```

## Admin shell

`SeoPanel` edits one record. Five more components build the rest of an admin
surface — a dashboard, a content list, redirect CRUD, a 404 monitor, and a
read-only settings view — each self-contained, fetching through the same
`SeoClient`:

```tsx
import { SeoDashboard, SeoContentList, SeoRedirects, SeoNotFoundMonitor, SeoSettings } from '@duxbo/seo-react'

<SeoDashboard client={client} onSelectType={(type) => router.push(`/admin/seo/content?type=${type}`)} />
<SeoContentList client={client} type="post" onEdit={(type, id) => router.push(`/admin/seo/${type}/${id}`)} />
<SeoRedirects client={client} />
<SeoNotFoundMonitor client={client} />
<SeoSettings client={client} />
```

None of them route — `onSelectType` and `onEdit` hand navigation back to the
host app rather than assuming a router. They talk to `/api/seo/v1`, the same
REST API `useMetaStore` uses, so they need `seo.api.enabled = true` and the
same Gate as everything else in this package. `NotFoundEntry.path` (and its
`referrer`/`user_agent`) is already HTML-escaped by the API — `SeoNotFoundMonitor`
renders it with `dangerouslySetInnerHTML` for that reason, not despite it: a
plain `{row.path}` would double-escape it into literal `&lt;` text instead of
the path Google actually requested.

## Tailwind

`SeoPanel` renders utility classes; it ships no CSS of its own. Add this
package's output to Tailwind's content globs, or the classes are purged and
the panel renders unstyled:

```js
// tailwind.config.js
content: ['./resources/**/*.blade.php', './node_modules/@duxbo/seo-react/dist/*.js']
```

## What this is not

`useMetaStore` force-renders on every store change rather than using
`useSyncExternalStore`'s snapshot-equality machinery — simpler, and correct for
an admin panel driven by explicit user actions, but not torn-proof under
React's concurrent renderer the way a stream of server-pushed data would need.
The trade-off is documented in the source rather than hidden.
