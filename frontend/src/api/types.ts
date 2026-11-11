// Response shapes of the backend API (spec 1.3, §6; README "Getting started").

export type Health = {
  status: 'ok' | 'error'
  database: 'ok' | 'unavailable'
  timestamp: string
}

export type SyncStatus = 'pending' | 'synced' | 'failed'

/** Mirrors backend `App\Enums\SyncErrorCode`. */
export type SyncErrorCode =
  | 'timeout'
  | 'unavailable'
  | 'rate_limited'
  | 'validation_failed'
  | 'unauthorized'

/** Order fields shared by the list and the detail view (1.3 §6.1). */
export type Order = {
  id: number
  number: string
  customer_name: string
  total_cents: number
  currency: string
  sync_status: SyncStatus
  external_order_id: string | null
  last_error_code: SyncErrorCode | null
  last_error_retryable: boolean | null
  attempts_count: number
  last_attempt_at: string | null
}

/**
 * Paginated order list (1.3 §6.2). Only the fields the client reads are
 * typed: `links` and `meta.links` are absolute URLs for the backend's own
 * host, not the page origin, and are deliberately ignored (2.1 §5.2).
 */
export type OrderList = {
  data: Order[]
  meta: {
    current_page: number
    last_page: number
    total: number
  }
}
