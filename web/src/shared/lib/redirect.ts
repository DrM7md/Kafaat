/**
 * تحصين ضدّ إعادة التوجيه المفتوح (open redirect): لا نتبع رابطاً إلّا إن
 * كان أصلُه أصلَنا أو أصل الـ API. رابط الدفع مصدرُه الخادم اليوم، لكن حين
 * تحلّ بوّابةٌ تجارية محلّ التطويرية يصير الرابط من طرفٍ خارجي — فالحاجز
 * يسبق ذلك اليوم.
 */
export function isAllowedRedirect(url: string): boolean {
  try {
    const target = new URL(url, window.location.origin)
    const allowed = new Set([window.location.origin])
    const apiUrl = import.meta.env.VITE_API_URL
    if (typeof apiUrl === 'string' && apiUrl !== '') {
      allowed.add(new URL(apiUrl).origin)
    }
    return allowed.has(target.origin)
  } catch {
    return false
  }
}
