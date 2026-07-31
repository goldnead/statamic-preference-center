@extends('preference-center::layout')

@section('title', __('preference-center::public.title'))

@section('content')
    {{--
        `data-*` attributes on every row and cell are there on purpose. They are
        what a check reads to say what the page is showing, instead of a person
        squinting at a screenshot — and they are the only reason a caption in a
        QA report can quote a state rather than assert one.
    --}}
    @php
        $rowErrorKeys = collect($view->lists ?? [])->map(fn ($row) => 'lists.'.$row->handle)
            ->merge(collect($view->types ?? [])->flatMap(fn ($row) => collect($view->channels)
                ->map(fn ($channel) => 'types.'.$row->type.'.'.$channel)))
            ->all();
        $loose = collect($errors->getMessages())
            ->reject(fn ($messages, $key) => in_array($key, $rowErrorKeys, true))
            ->flatten();
    @endphp

    <h1>{{ __('preference-center::public.title') }}</h1>
    <p class="lede">{{ __('preference-center::public.intro', ['email' => $view->access->email ?? '—']) }}</p>

    @if (session('preference-center.status'))
        <p class="notice" role="status">{{ session('preference-center.status') }}</p>
    @endif

    @if ($loose->isNotEmpty())
        <ul class="errors" role="alert">
            @foreach ($loose as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    @endif

    @if ($view->suppression->blocksMail())
        <p class="warn" data-suppression="{{ $view->suppression->unavailable ? 'unavailable' : 'blocked' }}">
            {{ $view->suppression->unavailable
                ? __('preference-center::public.suppression_unavailable')
                : __('preference-center::public.suppression_blocked') }}
        </p>
    @endif

    <form method="POST" action="{{ $back }}">
        @csrf
        <input type="hidden" name="action" value="save">

        @if ($view->hasLists())
            <input type="hidden" name="blocks[]" value="lists">
            <div class="block" data-block="lists">
                <h2>{{ __('preference-center::public.lists_heading') }}</h2>
                <p class="hint">{{ __('preference-center::public.lists_hint') }}</p>

                @if ($view->lists === [])
                    <p class="muted">{{ __('preference-center::public.lists_empty') }}</p>
                @else
                    <ul class="list">
                        @foreach ($view->lists as $row)
                            <li class="row{{ $row->blocked ? ' is-blocked' : '' }}"
                                data-list="{{ $row->handle }}" data-state="{{ $row->state() }}">
                                {{--
                                    No hidden twin for a blocked list row, and
                                    that is deliberate. `SubscriptionPreferences`
                                    leaves a blocked row alone in both
                                    directions and only objects when a
                                    submission asks for one to be switched *on*
                                    — so the browser's own behaviour, dropping
                                    the disabled box, is exactly right here, and
                                    carrying the handle back would manufacture
                                    the refusal instead of preventing it. The
                                    matrix below is the other way round; see
                                    there.
                                --}}
                                <label>
                                    <input type="checkbox" name="lists[]" value="{{ $row->handle }}"
                                        @checked($row->active) @disabled($row->blocked)>
                                    <span class="name">{{ $row->name }}</span>
                                </label>
                                @if ($row->description)
                                    <span class="desc">{{ $row->description }}</span>
                                @endif
                                @if ($row->blocked)
                                    <span class="blocked">{{ __('preference-center::public.lists_blocked') }}</span>
                                @endif
                                @error('lists.'.$row->handle)
                                    <span class="error">{{ $message }}</span>
                                @enderror
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif

        @if ($view->hasFrequency())
            <input type="hidden" name="blocks[]" value="frequency">
            <div class="block" data-block="frequency" data-frequency="{{ $view->frequency }}">
                <h2>{{ __('preference-center::public.frequency_heading') }}</h2>
                <p class="hint">{{ __('preference-center::public.frequency_hint') }}</p>

                @if ($view->frequency === \Goldnead\PreferenceCenter\Frequency::MIXED)
                    <p class="hint" data-frequency-mixed="yes">{{ __('preference-center::public.frequency_mixed') }}</p>
                @endif

                <ul class="choices">
                    @foreach (\Goldnead\PreferenceCenter\Frequency::all() as $choice)
                        @php $unavailable = $view->suppression->blocksMail() && $choice !== \Goldnead\PreferenceCenter\Frequency::NEVER; @endphp
                        <li data-choice="{{ $choice }}" data-selected="{{ $view->frequency === $choice ? 'yes' : 'no' }}">
                            <label>
                                <input type="radio" name="frequency" value="{{ $choice }}"
                                    @checked($view->frequency === $choice) @disabled($unavailable)>
                                <span>
                                    <span class="name">{{ __('preference-center::public.frequency_'.$choice) }}</span>
                                    <span class="desc">{{ __('preference-center::public.frequency_'.$choice.'_desc') }}</span>
                                </span>
                            </label>
                        </li>
                    @endforeach
                </ul>
                @error('frequency')
                    <span class="error">{{ $message }}</span>
                @enderror
            </div>
        @endif

        @if ($view->hasTypes())
            <input type="hidden" name="blocks[]" value="types">
            <div class="block" data-block="types">
                <h2>{{ __('preference-center::public.types_heading') }}</h2>
                <p class="hint">{{ __('preference-center::public.types_hint') }}</p>

                @if ($view->types === [])
                    <p class="muted">{{ __('preference-center::public.types_empty') }}</p>
                @else
                    <table class="matrix">
                        <thead>
                            <tr>
                                <th>{{ __('preference-center::public.types_column') }}</th>
                                @foreach ($view->channels as $channel)
                                    <th class="ch">{{ __('preference-center::public.channel_'.$channel) }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($view->types as $row)
                                <tr data-type="{{ $row->type }}"
                                    class="{{ $row->required ? 'is-required' : '' }}{{ $view->suppression->blocksMail() ? ' is-blocked' : '' }}">
                                    <td>
                                        <span class="name">{{ $row->label }}</span>
                                        @if ($row->required)
                                            <span class="why">{{ __('preference-center::public.types_required') }}</span>
                                        @endif
                                        @foreach ($view->channels as $channel)
                                            @error('types.'.$row->type.'.'.$channel)
                                                <span class="why is-error">{{ $message }}</span>
                                            @enderror
                                        @endforeach
                                    </td>
                                    @foreach ($view->channels as $channel)
                                        <td class="ch"
                                            data-cell="{{ $row->type }}.{{ $channel }}"
                                            data-state="{{ $row->isLocked($channel) ? 'locked-'.($row->isEnabled($channel) ? 'on' : 'off') : ($row->isEnabled($channel) ? 'on' : 'off') }}"
                                            data-reason="{{ $row->reason($channel) ?? '' }}">
                                            @if ($row->isLocked($channel) && $row->isEnabled($channel))
                                                {{--
                                                    What a disabled box does not say.

                                                    A browser omits a disabled checkbox from the
                                                    submission entirely, so a locked-on cell arrives
                                                    looking exactly like a cell somebody has just
                                                    switched off — and comes back refused, at a
                                                    control nobody touched. A blocked person who
                                                    pressed Save without changing anything got ten of
                                                    those, from the required types and the blocked
                                                    channels together.

                                                    This carries the state the page displayed, so an
                                                    untouched form posts what it showed. It changes
                                                    nothing about what may be written: the writer
                                                    re-reads every lock from the source, and a POST
                                                    that drops this field is refused exactly as it
                                                    was before.
                                                --}}
                                                <input type="hidden" name="types[{{ $row->type }}][{{ $channel }}]" value="1">
                                            @endif
                                            <input type="checkbox"
                                                name="types[{{ $row->type }}][{{ $channel }}]" value="1"
                                                @checked($row->isEnabled($channel)) @disabled($row->isLocked($channel))>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endif

        @if (! $view->hasLists() && ! $view->hasTypes())
            <p class="muted" data-block="none">{{ __('preference-center::public.nothing_installed') }}</p>
        @endif

        <button type="submit" class="btn" data-action="save">{{ __('preference-center::public.save') }}</button>
    </form>

    @if ($view->hasLists() && $view->lists !== [])
        {{--
            A second form, below a rule, with its own wording. It is not one
            checkbox among the others: it ends every list of this brand at once,
            and something with that reach should not be reachable by mis-clicking
            a row.
        --}}
        <form method="POST" action="{{ $back }}" class="all">
            @csrf
            <input type="hidden" name="action" value="unsubscribe_all">
            <h2>{{ __('preference-center::public.all_heading') }}</h2>
            <p class="hint">{{ __('preference-center::public.all_body') }}</p>
            <button type="submit" class="btn btn-quiet" data-action="unsubscribe-all">
                {{ __('preference-center::public.all_button') }}
            </button>
        </form>
    @endif

    <p class="foot" data-proof="{{ $view->access->proof }}">{{ __('preference-center::public.proof_'.$view->access->proof) }}</p>
@endsection
