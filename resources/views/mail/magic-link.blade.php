{{ __('preference-center::mail.magic_link_greeting') }}

{{ __('preference-center::mail.magic_link_body', ['minutes' => $minutes]) }}

{{--
    Unescaped, and it has to be. This is a plain-text body: there is no HTML
    context to escape into, and Blade's default would turn the `&` between
    `expires` and `signature` into `&amp;`. The URL still looks right to a
    reader and Laravel then rejects it as unsigned — a 403 on the one link the
    person asked for. Measured on the QA hub before this line said `!!`.
--}}
{!! $url !!}

{{ __('preference-center::mail.magic_link_ignore') }}
