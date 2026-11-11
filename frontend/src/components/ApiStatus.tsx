import { useEffect, useState } from 'react'
import { getHealth } from '../api/client.ts'

type Status = 'loading' | 'ok' | 'unavailable'

const labels: Record<Status, string> = {
  loading: 'Loading…',
  ok: 'ok',
  unavailable: 'unavailable',
}

/** Health of the browser → proxy → backend → database chain (spec 2.1, §5.1). */
export function ApiStatus() {
  const [status, setStatus] = useState<Status>('loading')

  useEffect(() => {
    const controller = new AbortController()

    getHealth(controller.signal)
      .then(() => {
        if (!controller.signal.aborted) setStatus('ok')
      })
      .catch(() => {
        if (!controller.signal.aborted) setStatus('unavailable')
      })

    return () => controller.abort()
  }, [])

  return <p className="api-status">{`API: ${labels[status]}`}</p>
}
