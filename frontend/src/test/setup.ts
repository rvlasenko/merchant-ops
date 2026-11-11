import '@testing-library/jest-dom/vitest'
import { cleanup } from '@testing-library/react'
import { afterEach, beforeEach, vi } from 'vitest'

// Tests never reach the backend: a fetch that the test did not stub is
// recorded and fails the test, even if the code under test swallows the
// rejection (spec 2.1, §7).
let unstubbedCalls: string[] = []

beforeEach(() => {
  unstubbedCalls = []
  vi.stubGlobal('fetch', (input: RequestInfo | URL) => {
    unstubbedCalls.push(input instanceof Request ? input.url : String(input))
    return Promise.reject(new Error('Unstubbed fetch'))
  })
})

afterEach(() => {
  cleanup()
  vi.unstubAllGlobals()
  if (unstubbedCalls.length > 0) {
    throw new Error(`Unstubbed fetch: ${unstubbedCalls.join(', ')}`)
  }
})
