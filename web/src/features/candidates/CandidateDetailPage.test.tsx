import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen, waitFor } from '@testing-library/react'
import { createMemoryRouter, RouterProvider } from 'react-router'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { Candidate, Paginated } from '@/shared/api/types'
import { CandidateDetailPage } from './CandidateDetailPage'
import { EMPTY_FILTERS } from './queries'

const { get } = vi.hoisted(() => ({ get: vi.fn() }))

vi.mock('@/shared/api/client', async () => {
  const axios = (await import('axios')).default

  return {
    api: { get, post: vi.fn() },
    apiErrorMessage: () => 'خطأ',
    isNotFound: (err: unknown) => axios.isAxiosError(err) && err.response?.status === 404,
  }
})

vi.mock('@/shared/hooks/usePublicSettings', () => ({
  useBillingUnderDevelopment: () => false,
}))

const LIST_CARD: Candidate = {
  ref: 'HNTYJ6XJ',
  title: 'مهندسة برمجيات',
  years_experience: 7,
  city: 'الدوحة',
  expected_salary: { min: 18000, max: 24000, currency: 'QAR' },
  is_featured: false,
  is_revealed: false,
  reveal_fee: 75,
  skills: ['Laravel', 'React'],
}

/** نفس ما يعيده التفصيل: بطاقة القائمة + النبذة والخبرات. */
const DETAIL: Candidate = { ...LIST_CARD, summary: 'خبرة في بناء أنظمة التوظيف.' }

function renderDetail(client: QueryClient) {
  const router = createMemoryRouter(
    [{ path: '/candidates/:ref', element: <CandidateDetailPage /> }],
    { initialEntries: [`/candidates/${LIST_CARD.ref}`] },
  )

  return render(
    <QueryClientProvider client={client}>
      <RouterProvider router={router} />
    </QueryClientProvider>,
  )
}

function newClient() {
  return new QueryClient({ defaultOptions: { queries: { retry: false } } })
}

/** يزرع صفحة تصفّح في الذاكرة كما لو أن المستخدم قادمٌ منها. */
function seedBrowseList(client: QueryClient) {
  const page: Paginated<Candidate> = {
    data: [LIST_CARD],
    meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 },
  }

  client.setQueryData(['candidates', 'list', EMPTY_FILTERS], page)
}

beforeEach(() => {
  get.mockReset()
})

describe('CandidateDetailPage — إحساس سطح المكتب', () => {
  it('★ قادماً من التصفّح: يرسم المحتوى فوراً بلا شاشة انتظار', async () => {
    // الطلب الخلفيّ لا يُحلّ أبداً في هذا الاختبار: لو كانت الصفحة تنتظره
    // لبقيت على الهيكل. نجاح الاختبار يعني أنها رسمت من البذرة وحدها.
    get.mockReturnValue(new Promise(() => {}))

    const client = newClient()
    seedBrowseList(client)

    renderDetail(client)

    expect(screen.getByText('مهندسة برمجيات')).toBeInTheDocument()
    expect(screen.getByText('هوية محجوبة')).toBeInTheDocument()
    expect(screen.queryByText('جارٍ التحميل…')).not.toBeInTheDocument()
  })

  it('البذرة لا تمنع إعادة الجلب: تصل التفاصيل الناقصة بعدها', async () => {
    get.mockResolvedValue({ data: { data: DETAIL } })

    const client = newClient()
    seedBrowseList(client)

    renderDetail(client)

    // النبذة غير موجودة في بطاقة القائمة — تظهر بعد وصول الطلب.
    expect(screen.queryByText(DETAIL.summary as string)).not.toBeInTheDocument()
    await waitFor(() => expect(screen.getByText(DETAIL.summary as string)).toBeInTheDocument())
    expect(get).toHaveBeenCalledWith(`/candidates/${LIST_CARD.ref}`)
  })

  it('بلا بذرة (فتحٌ مباشر بالرابط): يظهر الهيكل لا الفراغ', () => {
    get.mockReturnValue(new Promise(() => {}))

    renderDetail(newClient())

    expect(screen.queryByText('مهندسة برمجيات')).not.toBeInTheDocument()
    expect(document.querySelector('article')).toBeInTheDocument()
  })

  it('404 يمحو المحتوى المزروع — الملفّ لم يعد متاحاً', async () => {
    const notFound = Object.assign(new Error('Not Found'), {
      isAxiosError: true,
      response: { status: 404 },
      toJSON: () => ({}),
    })
    get.mockRejectedValue(notFound)

    const client = newClient()
    seedBrowseList(client)

    renderDetail(client)

    await waitFor(() => expect(screen.getByText('هذا الملفّ غير متاح')).toBeInTheDocument())
  })
})
