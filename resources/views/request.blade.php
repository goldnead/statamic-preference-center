@extends('preference-center::layout')

@section('title', __('preference-center::public.request_title'))

@section('content')
    <h1>{{ __('preference-center::public.request_title') }}</h1>
    <p class="lede">{{ __('preference-center::public.request_intro') }}</p>

    @if (session('preference-center.status'))
        {{--
            The same sentence for every outcome. Sent, no such address, blocked,
            throttled — this page cannot be used to find out which, and neither
            can a stopwatch: the controller holds the response to a floor.
        --}}
        <p class="notice" role="status" data-request-status="neutral">{{ session('preference-center.status') }}</p>
    @endif

    <form method="POST" action="{{ route('preference-center.request.send') }}" class="block">
        @csrf
        @if (! empty($brand))
            {{-- Carried across the redirect so the second page searches the same
                 audience the first one did. Naming a brand changes which lists a
                 link opens, never whether anything is revealed. --}}
            <input type="hidden" name="pcBrand" value="{{ $brand }}">
        @endif
        <label class="muted" for="pc-email">{{ __('preference-center::public.request_label') }}</label>
        <input id="pc-email" type="email" name="email" autocomplete="email" required
               placeholder="{{ __('preference-center::public.request_placeholder') }}">
        <button type="submit" class="btn" data-action="request-link">{{ __('preference-center::public.request_button') }}</button>
    </form>

    <p class="foot">{{ __('preference-center::public.request_foot', ['minutes' => (int) config('preference-center.magic_link.ttl_minutes', 30)]) }}</p>
@endsection
