import type { Order, OrderDetail, OrderList } from '../api/types.ts'

// Fixed API responses in the shapes of spec 1.3 §6. Hand-written, no random
// data (spec 2.1, §7).

export function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  })
}

export const pendingOrder: Order = {
  id: 1,
  number: 'MO-0001',
  customer_name: 'Ada Lovelace',
  total_cents: 1099,
  currency: 'EUR',
  sync_status: 'pending',
  external_order_id: null,
  last_error_code: null,
  last_error_retryable: null,
  attempts_count: 0,
  last_attempt_at: null,
}

export const syncedOrder: Order = {
  id: 2,
  number: 'MO-0002',
  customer_name: 'Alan Turing',
  total_cents: 2099,
  currency: 'EUR',
  sync_status: 'synced',
  external_order_id: 'EXT-MO-0002',
  last_error_code: null,
  last_error_retryable: null,
  attempts_count: 1,
  last_attempt_at: '2026-11-10T11:30:00+00:00',
}

export const failedRetryableOrder: Order = {
  id: 3,
  number: 'MO-0003',
  customer_name: 'Grace Hopper',
  total_cents: 3099,
  currency: 'EUR',
  sync_status: 'failed',
  external_order_id: null,
  last_error_code: 'timeout',
  last_error_retryable: true,
  attempts_count: 1,
  last_attempt_at: '2026-11-10T12:00:00+00:00',
}

export const failedNotRetryableOrder: Order = {
  id: 9,
  number: 'MO-0009',
  customer_name: 'Barbara Liskov',
  total_cents: 9099,
  currency: 'EUR',
  sync_status: 'failed',
  external_order_id: null,
  last_error_code: 'validation_failed',
  last_error_retryable: false,
  attempts_count: 2,
  last_attempt_at: '2026-11-10T12:05:30+00:00',
}

export const lastPageOrder: Order = {
  id: 16,
  number: 'MO-0016',
  customer_name: 'Tony Hoare',
  total_cents: 16099,
  currency: 'EUR',
  sync_status: 'pending',
  external_order_id: null,
  last_error_code: null,
  last_error_retryable: null,
  attempts_count: 0,
  last_attempt_at: null,
}

function orderList(data: Order[], currentPage: number, lastPage: number, total: number): OrderList {
  return { data, meta: { current_page: currentPage, last_page: lastPage, total } }
}

/** Unfiltered list, page 1 of 2. */
export const ordersPage1 = orderList(
  [pendingOrder, syncedOrder, failedRetryableOrder, failedNotRetryableOrder],
  1,
  2,
  5,
)

/** Unfiltered list, page 2 of 2. */
export const ordersPage2 = orderList([lastPageOrder], 2, 2, 5)

/** `sync_status=failed`, a single page. */
export const failedOrders = orderList([failedRetryableOrder, failedNotRetryableOrder], 1, 1, 2)

/** A filter that matches nothing. */
export const emptyOrders = orderList([], 1, 1, 0)

/** `GET /api/orders/{id}` wraps the order in `data` (1.3 §6.3). */
export function detailResponse(order: OrderDetail): { data: OrderDetail } {
  return { data: order }
}

/** Never synced: no simulation, empty log. */
export const pendingDetail: OrderDetail = {
  ...pendingOrder,
  simulated_failure: null,
  attempts: [],
}

/** Failed once on a one-time timeout; the simulation is used up. */
export const failedRetryableDetail: OrderDetail = {
  ...failedRetryableOrder,
  simulated_failure: { error_code: 'timeout', remaining: 0 },
  attempts: [
    {
      id: 1,
      trigger: 'initial',
      result: 'failed',
      error_code: 'timeout',
      error_message: 'External service did not respond in time.',
      retryable: true,
      created_at: '2026-11-10T12:00:00+00:00',
    },
  ],
}

/** Fails on every sync; two attempts, newest first. */
export const failedNotRetryableDetail: OrderDetail = {
  ...failedNotRetryableOrder,
  simulated_failure: { error_code: 'validation_failed', remaining: null },
  attempts: [
    {
      id: 7,
      trigger: 'retry',
      result: 'failed',
      error_code: 'validation_failed',
      error_message: 'External service rejected the order data.',
      retryable: false,
      created_at: '2026-11-10T12:05:30+00:00',
    },
    {
      id: 4,
      trigger: 'initial',
      result: 'failed',
      error_code: 'validation_failed',
      error_message: 'External service rejected the order data.',
      retryable: false,
      created_at: '2026-11-10T12:01:00+00:00',
    },
  ],
}

/** Synced on the first attempt. */
export const syncedDetail: OrderDetail = {
  ...syncedOrder,
  simulated_failure: null,
  attempts: [
    {
      id: 2,
      trigger: 'initial',
      result: 'success',
      error_code: null,
      error_message: null,
      retryable: null,
      created_at: '2026-11-10T11:30:00+00:00',
    },
  ],
}
