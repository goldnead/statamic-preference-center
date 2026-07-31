<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('preference-center::mail.magic_link_subject') }}</title>
</head>
{{--
    No layout, no build step, no images, no web fonts, no external stylesheet.
    Inline styles because a mail client is not a browser: half of them drop
    `<style>` blocks, and this mail has to be legible in the other half too.
--}}
<body style="margin:0; padding:0; background:#f4f4f5;">
<div style="max-width:560px; margin:0 auto; padding:32px 24px; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#18181b; font-size:15px; line-height:1.6;">

    <p style="margin:0 0 16px;">{{ __('preference-center::mail.magic_link_greeting') }}</p>

    <p style="margin:0 0 24px;">{{ __('preference-center::mail.magic_link_body', ['minutes' => $minutes]) }}</p>

    @if (count($links) > 1)
        <p style="margin:0 0 24px; color:#52525b;">{{ __('preference-center::mail.magic_link_several') }}</p>
    @endif

    @foreach ($links as $link)
        <div style="margin:0 0 24px;">
            @if (count($links) > 1 && $link['brand'])
                <p style="margin:0 0 8px; font-size:13px; text-transform:uppercase; letter-spacing:.04em; color:#71717a;">{{ $link['brand'] }}</p>
            @endif

            {{--
                Escaped, and it has to be — the exact opposite of the plain-text
                body, for the exact same reason. An attribute value *is* an HTML
                context: `&amp;` is how a `&` is spelled inside one, and every
                client turns it back into `&` before it opens anything. Writing
                `{!! !!}` here would emit a bare `&` in markup, which is a parse
                error the lenient clients repair and the strict ones do not.

                The regression this replaces was the mirror image: `{{ }}` in the
                text body, where there is no markup to repair, put a literal
                `&amp;` in the URL and Laravel answered 403. The test for this
                template does not read the href — it decodes it and follows it.
            --}}
            <a href="{{ $link['url'] }}"
               style="display:inline-block; background:#18181b; color:#ffffff; text-decoration:none; padding:12px 20px; border-radius:8px; font-size:15px;">{{ __('preference-center::mail.magic_link_button') }}</a>

            <p style="margin:12px 0 0; font-size:13px; color:#71717a;">{{ __('preference-center::mail.magic_link_fallback') }}</p>
            <p style="margin:4px 0 0; font-size:12px; color:#a1a1aa; word-break:break-all; line-height:1.5;">{{ $link['url'] }}</p>
        </div>
    @endforeach

    <p style="margin:24px 0 0; font-size:13px; color:#71717a;">{{ __('preference-center::mail.magic_link_ignore') }}</p>

</div>
</body>
</html>
