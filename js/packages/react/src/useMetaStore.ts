import { useEffect, useRef, useState } from 'react'
import { createMetaStore } from '@duxbo/seo-core'
import type { MetaStore, MetaStoreOptions, MetaStoreTarget, SeoClient } from '@duxbo/seo-core'

/**
 * Subscribes a MetaStore into React.
 *
 * The store is a single mutable object with getters — its identity never
 * changes, so there is nothing for `useSyncExternalStore`'s snapshot equality
 * to compare against without extra bookkeeping. A plain force-render counter,
 * bumped from the store's own `onChange`, does the same job with far less
 * code. The trade-off is honest, not hidden: this is not torn-proof under
 * React's concurrent renderer the way `useSyncExternalStore` would be. For an
 * admin panel driven by explicit user actions rather than streaming data, that
 * gap has never been worth the added complexity.
 */
export function useMetaStore(
  client: SeoClient,
  target: MetaStoreTarget,
  options?: MetaStoreOptions,
): MetaStore {
  const [, forceRender] = useState(0)
  const key = `${target.type}:${String(target.id)}:${target.locale ?? ''}`
  const holderRef = useRef<{ key: string; store: MetaStore } | null>(null)

  if (holderRef.current === null || holderRef.current.key !== key) {
    holderRef.current?.store.destroy()

    holderRef.current = {
      key,
      store: createMetaStore(client, target, {
        ...options,
        onChange: () => forceRender((n) => n + 1),
      }),
    }
  }

  useEffect(() => {
    // Reads the ref rather than closing over the store built during this
    // render: a target change between mount and this effect running has
    // already swapped it, and destroying the wrong one would cancel work the
    // component still depends on.
    return () => holderRef.current?.store.destroy()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  return holderRef.current.store
}
