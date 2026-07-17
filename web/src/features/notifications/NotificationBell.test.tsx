import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { createMemoryRouter, RouterProvider } from 'react-router'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { NotificationBell } from './NotificationBell'
import type { AppNotification, NotificationsResponse } from './queries'

const { get, post, can } = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), can: vi.fn() }))

vi.mock('@/shared/api/client', () => ({
  api: { get, post },
  apiErrorMessage: () => 'خطأ',
  isNotFound: () => false,
}))

vi.mock('@/shared/auth/AuthProvider', () => ({ useAuth: () => ({ can }) }))

const NEW: AppNotification = {
  id: 'n1',
  key: 'profile.approved',
  title: 'اعتُمد ملفّك',
  body: 'صار ملفّك ظاهراً للشركات.',
  link: '/profile',
  meta: {},
  read_at: null,
  created_at: new Date().toISOString(),
}

function inbox(rows: AppNotification[]): NotificationsResponse {
  return {
    data: rows,
    meta: { current_page: 1, last_page: 1, total: rows.length },
    unread_count: rows.filter((row) => row.read_at === null).length,
  }
}

function renderBell() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const router = createMemoryRouter(
    [
      { path: '/', element: <NotificationBell /> },
      { path: '/profile', element: <p>صفحة الملفّ</p> },
    ],
    { initialEntries: ['/'] },
  )

  return render(
    <QueryClientProvider client={client}>
      <RouterProvider router={router} />
    </QueryClientProvider>,
  )
}

beforeEach(() => {
  get.mockReset()
  post.mockReset()
  can.mockReset()

  can.mockReturnValue(false) // موظّف: لا صلاحية متابعة
  get.mockResolvedValue({ data: inbox([NEW]) })
  post.mockResolvedValue({ data: {} })
})

describe('جرس الإشعارات — قائمةٌ منسدلة لا صفحة', () => {
  it('★ النقر على الجرس يعرض الإشعارات في مكانها، لا ينتقل إلى صفحة', async () => {
    renderBell()

    // مغلقة ابتداءً — ما في الصفحة إلّا الجرس.
    await waitFor(() => expect(get).toHaveBeenCalledWith('/notifications', { params: { filter: 'new' } }))
    expect(screen.queryByText('اعتُمد ملفّك')).toBeNull()

    fireEvent.click(screen.getByRole('button', { name: /الإشعارات/ }))

    expect(await screen.findByText('اعتُمد ملفّك')).toBeInTheDocument()
    expect(screen.getByText('تعليم الكل كمقروء')).toBeInTheDocument()

    // ما زلنا في مكاننا: القائمة نزلت فوق الصفحة ولم تستبدلها.
    expect(screen.getByRole('menu')).toBeInTheDocument()
  })

  it('★ نقر الموظّف على الإشعار يُعلّمه مقروءاً فيغادر القائمة فوراً', async () => {
    renderBell()

    fireEvent.click(await screen.findByRole('button', { name: /الإشعارات/ }))

    // بعد النقر لا يعود الخادم يرسله: صار مقروءاً.
    get.mockResolvedValue({ data: inbox([]) })

    fireEvent.click(await screen.findByText('اعتُمد ملفّك'))

    await waitFor(() => expect(post).toHaveBeenCalledWith('/notifications/n1/read'))

    // اختفى — والانتقال إلى وجهته تمّ.
    await waitFor(() => expect(screen.queryByText('اعتُمد ملفّك')).toBeNull())
    expect(await screen.findByText('صفحة الملفّ')).toBeInTheDocument()
  })

  it('★ ذيل القائمة يقود الموظّف إلى أرشيف المقروء، والمشرفَ إلى صندوق المتابعة', async () => {
    const employee = renderBell()

    fireEvent.click(await screen.findByRole('button', { name: /الإشعارات/ }))
    expect(await screen.findByText('الإشعارات المقروءة')).toBeInTheDocument()

    employee.unmount()

    can.mockReturnValue(true) // مشرف
    renderBell()

    fireEvent.click(await screen.findByRole('button', { name: /الإشعارات/ }))
    expect(await screen.findByText('صندوق المتابعة الكامل')).toBeInTheDocument()
  })
})
