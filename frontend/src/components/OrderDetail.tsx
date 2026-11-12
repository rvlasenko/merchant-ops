import { useEffect, useId, useState } from 'react'
import { getOrder } from '../api/client.ts'
import type { OrderDetail as OrderDetailData, SimulatedFailure, SyncAttempt } from '../api/types.ts'
import { formatDateTime, formatMoney } from '../format.ts'

type Props = {
  orderId: number
  onClose: () => void
}

type Result =
  | { orderId: number; kind: 'loaded'; order: OrderDetailData }
  | { orderId: number; kind: 'error'; message: string }

/** Order detail panel: header, simulation and attempt log (spec 2.1, §5.3, §5.4, §5.6). */
export function OrderDetail({ orderId, onClose }: Props) {
  // A result for another order is not current: show Loading… instead (§5.4).
  const [result, setResult] = useState<Result | null>(null)
  const current = result?.orderId === orderId ? result : null

  useEffect(() => {
    const controller = new AbortController()

    getOrder(orderId, controller.signal)
      .then((order) => {
        if (!controller.signal.aborted) setResult({ orderId, kind: 'loaded', order })
      })
      .catch((error: unknown) => {
        if (controller.signal.aborted) return
        const message = error instanceof Error ? error.message : 'Request failed'
        setResult({ orderId, kind: 'error', message })
      })

    return () => controller.abort()
  }, [orderId])

  return (
    <aside className="order-detail" aria-label="Order detail">
      <button type="button" className="close" onClick={onClose}>
        Close
      </button>

      {current === null && <p>Loading…</p>}
      {current?.kind === 'error' && (
        <p role="alert" className="error">
          {current.message}
        </p>
      )}
      {current?.kind === 'loaded' && <Detail order={current.order} />}
    </aside>
  )
}

function Detail({ order }: { order: OrderDetailData }) {
  const attemptsHeading = useId()

  return (
    <>
      <h2>{order.number}</h2>
      <dl>
        <dt>Status</dt>
        <dd>
          <span className={`status status-${order.sync_status}`}>{order.sync_status}</span>
        </dd>
        {order.external_order_id !== null && (
          <>
            <dt>External order ID</dt>
            <dd>{order.external_order_id}</dd>
          </>
        )}
        <dt>Customer</dt>
        <dd>{order.customer_name}</dd>
        <dt>Total</dt>
        <dd>{formatMoney(order.total_cents, order.currency)}</dd>
        {/* Demo setting of the simulated external service, not order data. */}
        <dt>Simulated failure (demo)</dt>
        <dd>{simulation(order.simulated_failure)}</dd>
      </dl>

      <h3 id={attemptsHeading}>Attempts</h3>
      {order.attempts.length === 0 ? (
        <p>No attempts yet</p>
      ) : (
        <Attempts attempts={order.attempts} labelledBy={attemptsHeading} />
      )}
    </>
  )
}

function simulation(failure: SimulatedFailure | null): string {
  if (failure === null) return 'none'
  return `${failure.error_code}, remaining ${failure.remaining ?? 'always'}`
}

/** Rendered in API order: newest first (1.3 §6.3). */
function Attempts({ attempts, labelledBy }: { attempts: SyncAttempt[]; labelledBy: string }) {
  return (
    <table aria-labelledby={labelledBy}>
      <thead>
        <tr>
          <th>Time</th>
          <th>Trigger</th>
          <th>Result</th>
          <th>Error code</th>
          <th>Error message</th>
          <th>Retryable</th>
        </tr>
      </thead>
      <tbody>
        {attempts.map((attempt) => (
          <tr key={attempt.id}>
            <td>{formatDateTime(attempt.created_at)}</td>
            <td>{attempt.trigger}</td>
            <td>
              <span className={attempt.result === 'failed' ? 'status-failed' : 'status-synced'}>
                {attempt.result}
              </span>
            </td>
            <td>{attempt.error_code ?? ''}</td>
            <td>{attempt.error_message ?? ''}</td>
            <td>{attempt.retryable === null ? '' : attempt.retryable ? 'yes' : 'no'}</td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}
