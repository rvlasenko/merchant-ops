import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import type { SyncStatus } from '../api/types.ts'
import {
  emptyOrders,
  failedOrders,
  jsonResponse,
  ordersPage1,
  ordersPage2,
} from '../test/fixtures.ts'
import { OrderTable } from './OrderTable.tsx'

function renderTable(status: SyncStatus | null = null, page = 1) {
  const onFilterChange = vi.fn()
  const onPageChange = vi.fn()
  render(
    <OrderTable
      status={status}
      page={page}
      onFilterChange={onFilterChange}
      onPageChange={onPageChange}
    />,
  )
  return { onFilterChange, onPageChange }
}

function rowCells(number: string): string[] {
  const row = screen.getByRole('row', { name: new RegExp(number) })
  return within(row)
    .getAllByRole('cell')
    .map((cell) => cell.textContent ?? '')
}

describe('OrderTable', () => {
  it('requests the list for its status and page', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse(failedOrders))
    vi.stubGlobal('fetch', fetchMock)

    renderTable('failed', 1)

    expect(await screen.findByText('MO-0003')).toBeInTheDocument()
    expect(fetchMock).toHaveBeenCalledOnce()
    expect(fetchMock.mock.calls[0][0]).toBe('/api/orders?sync_status=failed&page=1')
  })

  it('renders one row per order with all columns', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(ordersPage1)))

    renderTable()

    expect(await screen.findByText('MO-0001')).toBeInTheDocument()
    const headers = screen.getAllByRole('columnheader').map((h) => h.textContent)
    expect(headers).toEqual([
      'Number',
      'Customer',
      'Total',
      'Status',
      'Last error',
      'Attempts',
      'Last attempt',
    ])
    // Header row + one row per order.
    expect(screen.getAllByRole('row')).toHaveLength(1 + ordersPage1.data.length)

    expect(rowCells('MO-0001')).toEqual([
      'MO-0001',
      'Ada Lovelace',
      '€10.99',
      'pending',
      '',
      '0',
      '',
    ])
    expect(rowCells('MO-0002')).toEqual([
      'MO-0002',
      'Alan Turing',
      '€20.99',
      'synced',
      '',
      '1',
      '10/11/2026, 11:30:00',
    ])
    expect(rowCells('MO-0003')).toEqual([
      'MO-0003',
      'Grace Hopper',
      '€30.99',
      'failed',
      'timeout (retryable)',
      '1',
      '10/11/2026, 12:00:00',
    ])
    expect(rowCells('MO-0009')).toEqual([
      'MO-0009',
      'Barbara Liskov',
      '€90.99',
      'failed',
      'validation_failed (not retryable)',
      '2',
      '10/11/2026, 12:05:30',
    ])
  })

  it('shows the current page and disables Prev on the first page', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(ordersPage1)))

    const { onPageChange } = renderTable()

    expect(await screen.findByText('Page 1 of 2')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /prev/i })).toBeDisabled()
    const next = screen.getByRole('button', { name: /next/i })
    expect(next).toBeEnabled()

    await userEvent.click(next)

    expect(onPageChange).toHaveBeenCalledExactlyOnceWith(2)
  })

  it('disables Next on the last page', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(ordersPage2)))

    const { onPageChange } = renderTable(null, 2)

    expect(await screen.findByText('Page 2 of 2')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /next/i })).toBeDisabled()

    await userEvent.click(screen.getByRole('button', { name: /prev/i }))

    expect(onPageChange).toHaveBeenCalledExactlyOnceWith(1)
  })

  it('reports the chosen filter, All as no filter', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(ordersPage1)))

    const { onFilterChange } = renderTable()
    await screen.findByText('MO-0001')

    const filter = screen.getByRole('combobox', { name: 'Status' })
    expect(filter).toHaveDisplayValue('All')

    await userEvent.selectOptions(filter, 'failed')
    expect(onFilterChange).toHaveBeenLastCalledWith('failed')

    await userEvent.selectOptions(filter, 'All')
    expect(onFilterChange).toHaveBeenLastCalledWith(null)
  })

  it('shows loading while the list request is pending', () => {
    vi.stubGlobal('fetch', vi.fn(() => new Promise<Response>(() => {})))

    renderTable()

    expect(screen.getByText('Loading…')).toBeInTheDocument()
    expect(screen.queryByRole('table')).not.toBeInTheDocument()
  })

  it('shows No orders for an empty list', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(emptyOrders)))

    renderTable('synced')

    expect(await screen.findByText('No orders')).toBeInTheDocument()
    expect(screen.queryByRole('table')).not.toBeInTheDocument()
  })

  it('shows the message from a 422 error body', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(
        jsonResponse({ message: 'The selected sync status is invalid.' }, 422),
      ),
    )

    renderTable('failed')

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'The selected sync status is invalid.',
    )
    expect(screen.queryByRole('table')).not.toBeInTheDocument()
  })

  it('shows Request failed for a 500 without JSON', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(new Response('Server Error', { status: 500 })),
    )

    renderTable()

    expect(await screen.findByRole('alert')).toHaveTextContent('Request failed (500)')
  })

  it('shows Network error when the request fails', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')))

    renderTable()

    expect(await screen.findByRole('alert')).toHaveTextContent('Network error')
  })
})
