import { describe, expect, it } from 'vitest'
import { formatDateTime, formatMoney } from './format.ts'

describe('formatMoney', () => {
  it('formats minor units as en-GB currency', () => {
    expect(formatMoney(3099, 'EUR')).toBe('€30.99')
    expect(formatMoney(16099, 'EUR')).toBe('€160.99')
  })
})

describe('formatDateTime', () => {
  // The test script sets TZ=UTC, so the browser time zone is fixed here.
  it('formats an ISO timestamp as short date and medium time', () => {
    expect(formatDateTime('2026-11-10T12:05:30+00:00')).toBe('10/11/2026, 12:05:30')
  })

  it('converts the offset into the local time zone', () => {
    expect(formatDateTime('2026-11-10T15:00:00+03:00')).toBe('10/11/2026, 12:00:00')
  })
})
