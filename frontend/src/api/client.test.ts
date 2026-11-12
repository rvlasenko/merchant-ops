import { describe, expect, it, vi } from 'vitest'
import {
  detailResponse,
  emptyOrders,
  failedRetryableDetail,
  jsonResponse,
  ordersPage1,
} from '../test/fixtures.ts'
import { getOrder, getOrders } from './client.ts'

describe('getOrder', () => {
  it('requests the order by a relative URL and unwraps data', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse(detailResponse(failedRetryableDetail)))
    vi.stubGlobal('fetch', fetchMock)

    await expect(getOrder(3)).resolves.toEqual(failedRetryableDetail)
    expect(fetchMock).toHaveBeenCalledOnce()
    expect(fetchMock.mock.calls[0][0]).toBe('/api/orders/3')
    expect(fetchMock.mock.calls[0][1]).toMatchObject({ method: 'GET' })
  })
})

describe('getOrders', () => {
  it('requests a relative URL with the filter and page', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse(emptyOrders))
    vi.stubGlobal('fetch', fetchMock)

    await getOrders('failed', 2)

    expect(fetchMock).toHaveBeenCalledOnce()
    expect(fetchMock.mock.calls[0][0]).toBe('/api/orders?sync_status=failed&page=2')
    expect(fetchMock.mock.calls[0][1]).toMatchObject({ method: 'GET' })
  })

  it('sends no sync_status without a filter', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse(ordersPage1))
    vi.stubGlobal('fetch', fetchMock)

    await expect(getOrders(null, 1)).resolves.toEqual(ordersPage1)
    expect(fetchMock.mock.calls[0][0]).toBe('/api/orders?page=1')
  })

  it('uses the message from a JSON error body', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(
        jsonResponse(
          {
            message: 'The selected sync status is invalid.',
            errors: { sync_status: ['The selected sync status is invalid.'] },
          },
          422,
        ),
      ),
    )

    await expect(getOrders('failed', 1)).rejects.toThrow('The selected sync status is invalid.')
  })

  it('falls back to the status code when the error body is not JSON', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(new Response('<html>Server Error</html>', { status: 500 })),
    )

    await expect(getOrders(null, 1)).rejects.toThrow('Request failed (500)')
  })

  it('reports a rejected fetch as a network error', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')))

    await expect(getOrders(null, 1)).rejects.toThrow('Network error')
  })
})
