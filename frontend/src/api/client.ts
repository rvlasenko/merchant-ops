import type { Health, OrderDetail, OrderList, SyncStatus } from './types.ts'

// The only place that calls fetch (spec 2.1, §6.1). Paths are relative, so the
// API must be served from the same origin as the page: in development the Vite
// proxy forwards /api to the backend (D4); a deployment must do the same.

export class ApiError extends Error {}

async function get<T>(path: string, signal?: AbortSignal): Promise<T> {
  let response: Response
  try {
    response = await fetch(path, {
      method: 'GET',
      headers: { Accept: 'application/json' },
      signal,
    })
  } catch (error) {
    // An aborted request is not an error to show (§5.6).
    if (signal?.aborted) throw error
    throw new ApiError('Network error')
  }

  if (!response.ok) {
    throw new ApiError(
      (await errorMessage(response)) ?? `Request failed (${response.status})`,
    )
  }

  return (await response.json()) as T
}

/** `message` from a JSON error body (Laravel's error shape), if there is one. */
async function errorMessage(response: Response): Promise<string | null> {
  try {
    const body: unknown = await response.json()
    if (
      typeof body === 'object' &&
      body !== null &&
      'message' in body &&
      typeof body.message === 'string' &&
      body.message !== ''
    ) {
      return body.message
    }
  } catch {
    // Not JSON: fall back to the status code.
  }
  return null
}

export function getHealth(signal?: AbortSignal): Promise<Health> {
  return get<Health>('/api/health', signal)
}

/**
 * The client builds the URL itself; `links` from the response are absolute
 * URLs built by the backend for its own host, not the page origin (§5.2).
 */
export function getOrders(
  status: SyncStatus | null,
  page: number,
  signal?: AbortSignal,
): Promise<OrderList> {
  const query = new URLSearchParams()
  if (status !== null) query.set('sync_status', status)
  query.set('page', String(page))

  return get<OrderList>(`/api/orders?${query}`, signal)
}

/** Order with its simulation and attempts; unwraps the resource `data` (1.3 §6.3). */
export async function getOrder(id: number, signal?: AbortSignal): Promise<OrderDetail> {
  const body = await get<{ data: OrderDetail }>(`/api/orders/${id}`, signal)
  return body.data
}
