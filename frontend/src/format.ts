// Display formatting (spec 2.1, §5.5). Fixed en-GB locale; time is shown in
// the browser's time zone (tests run with TZ=UTC).

const dateTimeFormat = new Intl.DateTimeFormat('en-GB', {
  dateStyle: 'short',
  timeStyle: 'medium',
})

/** Assumes two minor units: all data is EUR (spec 2.1, P3). */
export function formatMoney(cents: number, currency: string): string {
  return new Intl.NumberFormat('en-GB', { style: 'currency', currency }).format(cents / 100)
}

export function formatDateTime(iso: string): string {
  return dateTimeFormat.format(new Date(iso))
}
