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

/** Mirrors backend `App\Enums\AttemptTrigger`. */
export type AttemptTrigger = 'initial' | 'retry'

/** Mirrors backend `App\Enums\AttemptResult`. */
export type AttemptResult = 'success' | 'failed'

/** Demo setting of the simulated external service; `remaining: null` = always (1.3 §6.3). */
export type SimulatedFailure = {
  error_code: SyncErrorCode
  remaining: number | null
}

/** One entry of the attempt log; message and retryable are snapshots (1.3 §6.3). */
export type SyncAttempt = {
  id: number
  trigger: AttemptTrigger
  result: AttemptResult
  error_code: SyncErrorCode | null
  error_message: string | null
  retryable: boolean | null
  created_at: string
}

/** Order with its simulation and attempts, newest first (1.3 §6.3). */
export type OrderDetail = Order & {
  simulated_failure: SimulatedFailure | null
  attempts: SyncAttempt[]
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
