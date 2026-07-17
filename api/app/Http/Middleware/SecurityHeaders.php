<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ترويسات الأمان على كل استجابة من الـ API.
 *
 * الـ API يردّ JSON وتنزيلاتِ مرفقاتٍ فقط، فسياسةٌ صارمة لا تكسر شيئاً:
 *   - `nosniff`         يمنع المتصفّح من تخمين نوع محتوى التنزيل وتنفيذه.
 *   - `frame-ancestors` + `X-Frame-Options` يقطعان التأطير (clickjacking).
 *   - `default-src 'none'` لأنّ استجابة JSON لا تُحمِّل أي مورد أصلاً.
 *   - `HSTS` يُرسَل فوق HTTPS فقط — إرساله على HTTP بلا معنى وقد يُربك.
 *
 * ⚠️ حماية التأطير على **صفحات الواجهة** (SPA) مسؤوليةُ مضيفها الثابت لا
 *    هذا الحارس؛ راجع `web/public/.htaccess`. هذا الحارس دفاعٌ في العمق على
 *    مسار الـ API والتنزيلات.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
