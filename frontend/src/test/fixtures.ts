import type { Order, OrderList } from '../api/types.ts'

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
