import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import type { OrderDetail as OrderDetailData } from '../api/types.ts'
import {
  detailResponse,
  failedNotRetryableDetail,
  failedRetryableDetail,
  jsonResponse,
  pendingDetail,
  syncedDetail,
} from '../test/fixtures.ts'
import { OrderDetail } from './OrderDetail.tsx'

function respondWith(order: OrderDetailData) {
  const fetchMock = vi.fn().mockResolvedValue(jsonResponse(detailResponse(order)))
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

function renderDetail(orderId: number) {
  const onClose = vi.fn()
  const view = render(<OrderDetail orderId={orderId} onClose={onClose} />)
  return { onClose, ...view }
}

function panel(): HTMLElement {
  return screen.getByRole('complementary', { name: 'Order detail' })
}

/** Header fields as `term → definition`, in display order. */
function fields(): Record<string, string> {
  const terms = within(panel()).getAllByRole('term')
  const definitions = within(panel()).getAllByRole('definition')
  return Object.fromEntries(
    terms.map((term, i) => [term.textContent ?? '', definitions[i].textContent ?? '']),
  )
}

/** Attempt log rows without the header row, as cell texts. */
function attemptRows(): string[][] {
  const table = within(panel()).getByRole('table', { name: 'Attempts' })
  return within(table)
    .getAllByRole('row')
    .slice(1)
    .map((row) =>
      within(row)
        .getAllByRole('cell')
        .map((cell) => cell.textContent ?? ''),
    )
}

describe('OrderDetail', () => {
  it('requests the selected order', async () => {
    const fetchMock = respondWith(failedRetryableDetail)

    renderDetail(3)

    expect(await within(panel()).findByRole('heading', { name: 'MO-0003' })).toBeInTheDocument()
    expect(fetchMock).toHaveBeenCalledOnce()
    expect(fetchMock.mock.calls[0][0]).toBe('/api/orders/3')
  })

  it('shows the header and a used-up simulation', async () => {
    respondWith(failedRetryableDetail)

    renderDetail(3)
    await within(panel()).findByRole('heading', { name: 'MO-0003' })

    expect(fields()).toEqual({
      Status: 'failed',
      Customer: 'Grace Hopper',
      Total: '€30.99',
      'Simulated failure (demo)': 'timeout, remaining 0',
    })
  })

  it('shows the external order ID of a synced order and no simulation', async () => {
    respondWith(syncedDetail)

    renderDetail(2)
    await within(panel()).findByRole('heading', { name: 'MO-0002' })

    expect(fields()).toEqual({
      Status: 'synced',
      'External order ID': 'EXT-MO-0002',
      Customer: 'Alan Turing',
      Total: '€20.99',
      'Simulated failure (demo)': 'none',
    })
  })

  it('shows always for a simulation without a limit', async () => {
    respondWith(failedNotRetryableDetail)

    renderDetail(9)
    await within(panel()).findByRole('heading', { name: 'MO-0009' })

    expect(fields()['Simulated failure (demo)']).toBe('validation_failed, remaining always')
  })

  it('lists attempts newest first as the API returns them', async () => {
    respondWith(failedNotRetryableDetail)

    renderDetail(9)
    await within(panel()).findByRole('heading', { name: 'MO-0009' })

    const headers = within(within(panel()).getByRole('table', { name: 'Attempts' }))
      .getAllByRole('columnheader')
      .map((h) => h.textContent)
    expect(headers).toEqual(['Time', 'Trigger', 'Result', 'Error code', 'Error message', 'Retryable'])
    expect(attemptRows()).toEqual([
      [
        '10/11/2026, 12:05:30',
        'retry',
        'failed',
        'validation_failed',
        'External service rejected the order data.',
        'no',
      ],
      [
        '10/11/2026, 12:01:00',
        'initial',
        'failed',
        'validation_failed',
        'External service rejected the order data.',
        'no',
      ],
    ])
  })

  it('leaves error cells empty for a successful attempt', async () => {
    respondWith(syncedDetail)

    renderDetail(2)
    await within(panel()).findByRole('heading', { name: 'MO-0002' })

    expect(attemptRows()).toEqual([['10/11/2026, 11:30:00', 'initial', 'success', '', '', '']])
  })

  it('shows No attempts yet for an empty log', async () => {
    respondWith(pendingDetail)

    renderDetail(1)
    await within(panel()).findByRole('heading', { name: 'MO-0001' })

    expect(within(panel()).getByText('No attempts yet')).toBeInTheDocument()
    expect(within(panel()).queryByRole('table')).not.toBeInTheDocument()
  })

  it('reports Close', async () => {
    respondWith(failedRetryableDetail)

    const { onClose } = renderDetail(3)
    await within(panel()).findByRole('heading', { name: 'MO-0003' })

    await userEvent.click(within(panel()).getByRole('button', { name: 'Close' }))

    expect(onClose).toHaveBeenCalledOnce()
  })

  it('shows loading while the detail request is pending', () => {
    vi.stubGlobal('fetch', vi.fn(() => new Promise<Response>(() => {})))

    renderDetail(3)

    expect(within(panel()).getByText('Loading…')).toBeInTheDocument()
    expect(within(panel()).queryByRole('heading', { name: 'MO-0003' })).not.toBeInTheDocument()
  })

  it('shows the message from a 404 error body', async () => {
    vi.stubGlobal(
      'fetch',
      vi
        .fn()
        .mockResolvedValue(
          jsonResponse({ message: 'No query results for model [App\\Models\\Order] 999' }, 404),
        ),
    )

    renderDetail(999)

    expect(await within(panel()).findByRole('alert')).toHaveTextContent(
      'No query results for model [App\\Models\\Order] 999',
    )
  })

  it('shows Request failed for a 500 without JSON', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('Server Error', { status: 500 })))

    renderDetail(3)

    expect(await within(panel()).findByRole('alert')).toHaveTextContent('Request failed (500)')
  })

  it('shows Network error when the request fails', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')))

    renderDetail(3)

    expect(await within(panel()).findByRole('alert')).toHaveTextContent('Network error')
  })

  it('ignores a detail response that arrives after the selection changed', async () => {
    const pending: { url: string; init: RequestInit; respond: (order: OrderDetailData) => void }[] =
      []
    vi.stubGlobal(
      'fetch',
      vi.fn(
        (input: RequestInfo | URL, init: RequestInit = {}) =>
          new Promise<Response>((resolve) => {
            pending.push({
              url: String(input),
              init,
              respond: (order) => resolve(jsonResponse(detailResponse(order))),
            })
          }),
      ),
    )

    const { rerender, onClose } = renderDetail(3)
    rerender(<OrderDetail orderId={9} onClose={onClose} />)

    expect(pending.map((p) => p.url)).toEqual(['/api/orders/3', '/api/orders/9'])
    expect(pending[0].init.signal?.aborted).toBe(true)

    pending[1].respond(failedNotRetryableDetail)
    expect(await within(panel()).findByRole('heading', { name: 'MO-0009' })).toBeInTheDocument()

    // The stale response arrives last; the stub ignores the abort signal.
    pending[0].respond(failedRetryableDetail)
    await new Promise((resolve) => setTimeout(resolve, 0))

    expect(within(panel()).queryByRole('heading', { name: 'MO-0003' })).not.toBeInTheDocument()
    expect(within(panel()).getByRole('heading', { name: 'MO-0009' })).toBeInTheDocument()
    expect(within(panel()).queryByRole('alert')).not.toBeInTheDocument()
  })
})
