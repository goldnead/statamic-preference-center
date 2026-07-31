{{ __('preference-center::mail.magic_link_greeting') }}

{{ __('preference-center::mail.magic_link_body', ['minutes' => $minutes]) }}
@if (count($links) > 1)

{{ __('preference-center::mail.magic_link_several') }}
@endif
@foreach ($links as $link)
@if (count($links) > 1 && $link['brand'])

{{ $link['brand'] }}:
@else

@endif
{{--
    Unescaped, and it has to be. This is a plain-text body: there is no HTML
    context to escape into, and Blade's default would turn the `&` between
    `expires` and `signature` into `&amp;`. The URL still looks right to a
    reader and Laravel then rejects it as unsigned — a 403 on the one link the
    person asked for. Measured on the QA hub before this line said `!!`.

    The HTML body escapes the same URL, and has to. See the note there: an
    attribute is an HTML context, `&amp;` is what a `&` is called inside one,
    and the client turns it back before it ever reaches Laravel.
--}}
{!! $link['url'] !!}
@endforeach

{{ __('preference-center::mail.magic_link_ignore') }}
