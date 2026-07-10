import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { createMemoryRouter, RouterProvider } from 'react-router'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { Candidate, Paginated } from '@/shared/api/types'
import { BrowsePage } from './BrowsePage'

const { get } = vi.hoisted(() => ({ get: vi.fn() }))

vi.mock('@/shared/api/client', () => ({
  api: { get, post: vi.fn() },
  apiErrorMessage: () => 'خطأ',
  isNotFound: () => false,
}))

vi.mock('@/shared/hooks/usePublicSettings', () => ({
  useBillingUnderDevelopment: () => false,
}))

const EMPTY_PAGE: Paginated<Candidate> = {
  data: [],
  meta: { total: 0, current_page: 1, last_page: 1, per_page: 20 },
}

function renderBrowse() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const router = createMemoryRouter([{ path: '/', element: <BrowsePage /> }], { initialEntries: ['/'] })

  return render(
    <QueryClientProvider client={client}>
      <RouterProvider router={router} />
    </QueryClientProvider>,
  )
}

const candidateCalls = () => get.mock.calls.filter(([url]) => url === '/candidates').length

beforeEach(() => {
  get.mockReset()
  get.mockImplementation((url: string) =>
    url === '/lookups'
      ? Promise.resolve({ data: { lookups: {} } })
      : Promise.resolve({ data: EMPTY_PAGE }),
  )
})

describe('BrowsePage — البحث لا يُغرق الخادم', () => {
  it('★ كتابة «مهندس» تُطلق طلباً واحداً لا ستّة', async () => {
    renderBrowse()

    await waitFor(() => expect(candidateCalls()).toBe(1)) // الجلب الأوّل

    const input = screen.getByPlaceholderText('ابحث بالمهارة، المجال، المسمّى…')
    const word = 'مهندس'

    // ست ضغطاتٍ متتابعة أسرع من مهلة التأخير.
    for (let i = 1; i <= word.length; i++) {
      fireEvent.change(input, { target: { value: word.slice(0, i) } })
    }

    // قبل انقضاء المهلة: لا طلب جديد رغم خمس ضغطات.
    expect(candidateCalls()).toBe(1)

    // بعدها: طلبٌ واحد بالكلمة كاملة.
    await waitFor(() => expect(candidateCalls()).toBe(2), { timeout: 2000 })

    expect(get).toHaveBeenLastCalledWith('/candidates', { params: { q: word } })
  })
})
