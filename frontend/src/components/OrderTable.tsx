import { useEffect, useState } from 'react'
import { getOrders } from '../api/client.ts'
import type { Order, OrderList, SyncStatus } from '../api/types.ts'
import { formatDateTime, formatMoney } from '../format.ts'

type Props = {
  status: SyncStatus | null
  page: number
  onFilterChange: (status: SyncStatus | null) => void
  onPageChange: (page: number) => void
  selectedId: number | null
  onSelect: (id: number) => void
}

type Result =
  | { key: string; kind: 'loaded'; list: OrderList }
  | { key: string; kind: 'error'; message: string }

const statuses: SyncStatus[] = ['pending', 'synced', 'failed']

/** Order list with status filter and pagination (spec 2.1, §5.2, §5.4, §5.6). */
export function OrderTable({
  status,
  page,
  onFilterChange,
  onPageChange,
  selectedId,
  onSelect,
}: Props) {
  // The result remembers which request it answers. A result for other props
  // is not current, so the component shows Loading… instead of old rows.
  const key = `${status ?? ''}|${page}`
  const [result, setResult] = useState<Result | null>(null)
  const current = result?.key === key ? result : null

  useEffect(() => {
    const controller = new AbortController()

    getOrders(status, page, controller.signal)
      .then((list) => {
        if (!controller.signal.aborted) setResult({ key, kind: 'loaded', list })
      })
      .catch((error: unknown) => {
        if (controller.signal.aborted) return
        const message = error instanceof Error ? error.message : 'Request failed'
        setResult({ key, kind: 'error', message })
      })

    return () => controller.abort()
  }, [key, status, page])

  return (
    <section className="order-list">
      <label className="filter">
        Status{' '}
        <select
          value={status ?? ''}
          onChange={(event) =>
            onFilterChange(event.target.value === '' ? null : (event.target.value as SyncStatus))
          }
        >
          <option value="">All</option>
          {statuses.map((s) => (
            <option key={s} value={s}>
              {s}
            </option>
          ))}
        </select>
      </label>

      {current === null && <p>Loading…</p>}
      {current?.kind === 'error' && (
        <p role="alert" className="error">
          {current.message}
        </p>
      )}
      {current?.kind === 'loaded' && (
        <>
          {current.list.data.length === 0 ? (
            <p>No orders</p>
          ) : (
            <Table orders={current.list.data} selectedId={selectedId} onSelect={onSelect} />
          )}
          <Pagination
            currentPage={current.list.meta.current_page}
            lastPage={current.list.meta.last_page}
            onPageChange={onPageChange}
          />
        </>
      )}
    </section>
  )
}

type TableProps = {
  orders: Order[]
  selectedId: number | null
  onSelect: (id: number) => void
}

/**
 * Clicking a row selects the order (§5.2). The number is also a button, so
 * the order can be selected from the keyboard; its click bubbles to the row.
 */
function Table({ orders, selectedId, onSelect }: TableProps) {
  return (
    <table>
      <thead>
        <tr>
          <th>Number</th>
          <th>Customer</th>
          <th>Total</th>
          <th>Status</th>
          <th>Last error</th>
          <th>Attempts</th>
          <th>Last attempt</th>
        </tr>
      </thead>
      <tbody>
        {orders.map((order) => (
          <tr
            key={order.id}
            className={order.id === selectedId ? 'selected' : undefined}
            aria-current={order.id === selectedId ? 'true' : undefined}
            onClick={() => onSelect(order.id)}
          >
            <td>
              <button type="button" className="row-select">
                {order.number}
              </button>
            </td>
            <td>{order.customer_name}</td>
            <td className="number">{formatMoney(order.total_cents, order.currency)}</td>
            <td>
              <span className={`status status-${order.sync_status}`}>{order.sync_status}</span>
            </td>
            <td>{lastError(order)}</td>
            <td className="number">{order.attempts_count}</td>
            <td>{order.last_attempt_at === null ? '' : formatDateTime(order.last_attempt_at)}</td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}

function lastError(order: Order): string {
  if (order.last_error_code === null) return ''
  return `${order.last_error_code} (${order.last_error_retryable ? 'retryable' : 'not retryable'})`
}

type PaginationProps = {
  currentPage: number
  lastPage: number
  onPageChange: (page: number) => void
}

/** Builds pages from `meta` only; `links` are never followed (§5.2, R4). */
function Pagination({ currentPage, lastPage, onPageChange }: PaginationProps) {
  return (
    <nav className="pagination" aria-label="Pagination">
      <button
        type="button"
        disabled={currentPage <= 1}
        onClick={() => onPageChange(currentPage - 1)}
      >
        ‹ Prev
      </button>
      <span>{`Page ${currentPage} of ${lastPage}`}</span>
      <button
        type="button"
        disabled={currentPage >= lastPage}
        onClick={() => onPageChange(currentPage + 1)}
      >
        Next ›
      </button>
    </nav>
  )
}
