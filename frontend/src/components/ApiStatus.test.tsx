import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { ApiStatus } from './ApiStatus.tsx'

function jsonResponse(body: unknown, status: number): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  })
}

describe('ApiStatus', () => {
  it('shows loading while the health check is pending', () => {
    vi.stubGlobal('fetch', vi.fn(() => new Promise<Response>(() => {})))

    render(<ApiStatus />)

    expect(screen.getByText('API: Loading…')).toBeInTheDocument()
  })

  it('shows ok when the health check answers 200', async () => {
    const fetchMock = vi.fn().mockResolvedValue(
      jsonResponse({ status: 'ok', database: 'ok', timestamp: '2026-11-10T12:00:00+00:00' }, 200),
    )
    vi.stubGlobal('fetch', fetchMock)

    render(<ApiStatus />)

    expect(await screen.findByText('API: ok')).toBeInTheDocument()
    expect(fetchMock).toHaveBeenCalledOnce()
    expect(fetchMock.mock.calls[0][0]).toBe('/api/health')
    expect(fetchMock.mock.calls[0][1]).toMatchObject({ method: 'GET' })
  })

  it('shows unavailable when the health check answers 503', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(
        jsonResponse({ status: 'error', database: 'unavailable', timestamp: '2026-11-10T12:00:00+00:00' }, 503),
      ),
    )

    render(<ApiStatus />)

    expect(await screen.findByText('API: unavailable')).toBeInTheDocument()
  })

  it('shows unavailable when the request fails', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')))

    render(<ApiStatus />)

    expect(await screen.findByText('API: unavailable')).toBeInTheDocument()
  })
})
