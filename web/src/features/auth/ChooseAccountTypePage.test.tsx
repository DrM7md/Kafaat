import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { createMemoryRouter, RouterProvider } from 'react-router'
import { describe, expect, it } from 'vitest'
import { ChooseAccountTypePage } from './ChooseAccountTypePage'

/** يحاكي الشجرة الحقيقية: المفترق ثمّ نموذجان مميّزان بنصّهما. */
function renderChooser() {
  const router = createMemoryRouter(
    [
      { path: '/register', element: <ChooseAccountTypePage /> },
      { path: '/register/employee', element: <p>نموذج الباحث عن عمل</p> },
      { path: '/register/company', element: <p>نموذج الشركة</p> },
    ],
    { initialEntries: ['/register'] },
  )

  return render(<RouterProvider router={router} />)
}

describe('ChooseAccountTypePage — مفترق التسجيل', () => {
  it('★ بطاقة الباحث عن عمل تقود إلى نموذج الموظّف', async () => {
    renderChooser()

    fireEvent.click(screen.getByRole('link', { name: /أبحث عن فرصة أفضل/ }))

    await waitFor(() => expect(screen.getByText('نموذج الباحث عن عمل')).toBeInTheDocument())
  })

  it('★ بطاقة الشركة تقود إلى نموذج الشركة', async () => {
    renderChooser()

    fireEvent.click(screen.getByRole('link', { name: /أبحث عن موظّف/ }))

    await waitFor(() => expect(screen.getByText('نموذج الشركة')).toBeInTheDocument())
  })

  it('الخياران وحدهما — لا ثالث يتسلّل إلى المفترق', () => {
    renderChooser()

    // رابطا البطاقتين + رابط «سجّل دخولك». أي رابعٍ يعني خياراً لم يُقصد.
    expect(screen.getAllByRole('link')).toHaveLength(3)
  })

  it('يشرح الحجب للباحث عن عمل قبل أن يختار — لا بعد', () => {
    renderChooser()

    expect(screen.getByText(/محجوبة/)).toBeInTheDocument()
  })
})
