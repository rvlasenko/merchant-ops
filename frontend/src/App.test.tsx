import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import App from './App.tsx'
import type { OrderDetail, OrderList } from './api/types.ts'
import {
  detailResponse,
  failedNotRetryableDetail,
  failedOrders,
  failedRetryableDetail,
  jsonResponse,
  ordersPage1,
  ordersPage2,
} from './test/fixtures.ts'

const health = { status: 'ok', database: 'ok', timestamp: '2026-11-10T12:00:00+00:00' }

const details: Record<string, OrderDetail> = {
  '/api/orders/3': failedRetryableDetail,
  '/api/orders/9': failedNotRetryableDetail,
}

type Deferred = { resolve: (body: OrderList) => void }

/**
 * Stubs fetch for the whole page: health and order details answer at once,
 * each order list request waits until the test resolves it. Returns the list
 * requests in the order they were made.
 */
function stubApi() {
  const listRequests: { url: string; init: RequestInit; respond: Deferred['resolve'] }[] = []
  const fetchMock = vi.fn((input: RequestInfo | URL, init: RequestInit = {}) => {
    const url = String(input)
    if (url === '/api/health') return Promise.resolve(jsonResponse(health))
    if (url in details) return Promise.resolve(jsonResponse(detailResponse(details[url])))
    return new Promise<Response>((resolve) => {
      listRequests.push({ url, init, respond: (body) => resolve(jsonResponse(body)) })
    })
  })
  vi.stubGlobal('fetch', fetchMock)
  return { fetchMock, listRequests }
}

describe('App order list', () => {
  it('requests the first unfiltered page on load', async () => {
    const { listRequests } = stubApi()

    render(<App />)
    await screen.findByText('API: ok')

    expect(listRequests.map((r) => r.url)).toEqual(['/api/orders?page=1'])
  })

  it('requests page 2 on Next and hides page 1 while it loads', async () => {
    const { listRequests } = stubApi()
    render(<App />)
    await screen.findByText('Loading…')
    listRequests[0].respond(ordersPage1)
    await screen.findByText('MO-0001')

    await userEvent.click(screen.getByRole('button', { name: /next/i }))

    expect(listRequests[1].url).toBe('/api/orders?page=2')
    expect(screen.getByText('Loading…')).toBeInTheDocument()
    expect(screen.queryByText('MO-0001')).not.toBeInTheDocument()

    listRequests[1].respond(ordersPage2)
    expect(await screen.findByText('MO-0016')).toBeInTheDocument()
    expect(screen.getByText('Page 2 of 2')).toBeInTheDocument()
  })

  it('requests the filtered list and resets to page 1 when the filter changes', async () => {
    const { listRequests } = stubApi()
    render(<App />)
    await screen.findByText('Loading…')
    listRequests[0].respond(ordersPage1)
    await userEvent.click(await screen.findByRole('button', { name: /next/i }))
    listRequests[1].respond(ordersPage2)
    await screen.findByText('Page 2 of 2')

    await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Status' }), 'failed')

    expect(listRequests[2].url).toBe('/api/orders?sync_status=failed&page=1')

    listRequests[2].respond(failedOrders)
    expect(await screen.findByText('Page 1 of 1')).toBeInTheDocument()

    await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Status' }), 'All')

    expect(listRequests[3].url).toBe('/api/orders?page=1')
  })

  it('ignores a list response that arrives after the filter changed', async () => {
    const { listRequests } = stubApi()
    render(<App />)
    await screen.findByText('Loading…')

    // The first request is still pending when the filter changes.
    await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Status' }), 'failed')
    expect(listRequests).toHaveLength(2)
    expect(listRequests[0].init.signal?.aborted).toBe(true)

    listRequests[1].respond(failedOrders)
    expect(await screen.findByText('MO-0003')).toBeInTheDocument()

    // The stale response arrives last; the stub ignores the abort signal,
    // like a response already on its way.
    listRequests[0].respond(ordersPage1)
    await Promise.resolve()
    await new Promise((resolve) => setTimeout(resolve, 0))

    expect(screen.queryByText('MO-0001')).not.toBeInTheDocument()
    expect(screen.getByText('MO-0003')).toBeInTheDocument()
    expect(screen.getByText('Page 1 of 1')).toBeInTheDocument()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('issues only GET requests', async () => {
    const { fetchMock, listRequests } = stubApi()
    render(<App />)
    await screen.findByText('Loading…')
    listRequests[0].respond(ordersPage1)
    await userEvent.click(await screen.findByRole('row', { name: /MO-0003/ }))
    await findDetailHeading('MO-0003')
    await userEvent.click(screen.getByRole('button', { name: /next/i }))
    listRequests[1].respond(ordersPage2)
    await screen.findByText('MO-0016')

    const urls = fetchMock.mock.calls.map(([input]) => String(input))
    expect(urls).toContain('/api/orders/3')
    for (const [, init] of fetchMock.mock.calls) {
      expect(init?.method).toBe('GET')
    }
  })
})

function detailPanel(): HTMLElement | null {
  return screen.queryByRole('complementary', { name: 'Order detail' })
}

async function findDetailHeading(number: string): Promise<HTMLElement> {
  const panel = await screen.findByRole('complementary', { name: 'Order detail' })
  return within(panel).findByRole('heading', { name: number })
}

describe('App order detail', () => {
  async function renderWithList() {
    const api = stubApi()
    render(<App />)
    await screen.findByText('Loading…')
    api.listRequests[0].respond(ordersPage1)
    await screen.findByText('MO-0003')
    return api
  }

  it('has no detail panel until an order is selected', async () => {
    const { fetchMock } = await renderWithList()

    expect(detailPanel()).not.toBeInTheDocument()
    expect(fetchMock.mock.calls.map(([input]) => String(input))).not.toContain('/api/orders/3')
  })

  it('opens the clicked order and highlights its row', async () => {
    const { fetchMock } = await renderWithList()

    await userEvent.click(screen.getByRole('row', { name: /MO-0003/ }))

    expect(await findDetailHeading('MO-0003')).toBeInTheDocument()
    expect(fetchMock.mock.calls.map(([input]) => String(input))).toContain('/api/orders/3')
    expect(screen.getByRole('row', { name: /MO-0003/ })).toHaveAttribute('aria-current', 'true')
  })

  it('switches the panel to another clicked order', async () => {
    await renderWithList()
    await userEvent.click(screen.getByRole('row', { name: /MO-0003/ }))
    await findDetailHeading('MO-0003')

    await userEvent.click(screen.getByRole('row', { name: /MO-0009/ }))

    expect(await findDetailHeading('MO-0009')).toBeInTheDocument()
    expect(screen.getByRole('row', { name: /MO-0003/ })).not.toHaveAttribute('aria-current')
  })

  it('clears the selection on Close', async () => {
    await renderWithList()
    await userEvent.click(screen.getByRole('row', { name: /MO-0003/ }))
    const panel = await screen.findByRole('complementary', { name: 'Order detail' })

    await userEvent.click(await within(panel).findByRole('button', { name: 'Close' }))

    expect(detailPanel()).not.toBeInTheDocument()
    expect(screen.getByRole('row', { name: /MO-0003/ })).not.toHaveAttribute('aria-current')
  })

  it('keeps the panel open when the filter or page changes', async () => {
    const { listRequests } = await renderWithList()
    await userEvent.click(screen.getByRole('row', { name: /MO-0003/ }))
    await findDetailHeading('MO-0003')

    await userEvent.click(screen.getByRole('button', { name: /next/i }))
    listRequests[1].respond(ordersPage2)
    await screen.findByText('MO-0016')

    expect(await findDetailHeading('MO-0003')).toBeInTheDocument()

    await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Status' }), 'failed')
    listRequests[2].respond(failedOrders)
    await screen.findByText('Page 1 of 1')

    expect(await findDetailHeading('MO-0003')).toBeInTheDocument()
  })
})
