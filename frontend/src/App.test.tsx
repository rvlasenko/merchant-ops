import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import App from './App.tsx'
import type { OrderList } from './api/types.ts'
import { failedOrders, jsonResponse, ordersPage1, ordersPage2 } from './test/fixtures.ts'

const health = { status: 'ok', database: 'ok', timestamp: '2026-11-10T12:00:00+00:00' }

type Deferred = { resolve: (body: OrderList) => void }

/**
 * Stubs fetch for the whole page: health answers at once, each order list
 * request waits until the test resolves it. Returns the list requests in the
 * order they were made.
 */
function stubApi() {
  const listRequests: { url: string; init: RequestInit; respond: Deferred['resolve'] }[] = []
  const fetchMock = vi.fn((input: RequestInfo | URL, init: RequestInit = {}) => {
    const url = String(input)
    if (url === '/api/health') return Promise.resolve(jsonResponse(health))
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
    await userEvent.click(await screen.findByRole('button', { name: /next/i }))
    listRequests[1].respond(ordersPage2)
    await screen.findByText('MO-0016')

    expect(fetchMock.mock.calls.length).toBeGreaterThan(0)
    for (const [, init] of fetchMock.mock.calls) {
      expect(init?.method).toBe('GET')
    }
  })
})
