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
