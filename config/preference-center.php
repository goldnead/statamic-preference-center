<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | The public pages. The prefix is deliberately not `/preferences`: a host
    | that already owns that URL should not have to fight this addon for it.
    |
    */

    'routes' => [
        'enabled' => env('PREFERENCE_CENTER_ROUTES', true),
        'prefix' => env('PREFERENCE_CENTER_ROUTE_PREFIX', '!/preference-center'),
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sources
    |--------------------------------------------------------------------------
    |
    | Each block comes from a package that may or may not be installed. `auto`
    | asks the container and the class map; `true` and `false` override that.
    |
    | Turning a source off does not hide it from a determined poster: the write
    | paths refuse an absent source as firmly as the render path omits it.
    |
    */

    'sources' => [
        'marketing' => env('PREFERENCE_CENTER_SOURCE_MARKETING', 'auto'),
        'notifications' => env('PREFERENCE_CENTER_SOURCE_NOTIFICATIONS', 'auto'),
        'suppression' => env('PREFERENCE_CENTER_SOURCE_SUPPRESSION', 'auto'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Magic link
    |--------------------------------------------------------------------------
    |
    | The third way in, for the person who has no account and no old mail.
    |
    | `ttl_minutes` is the life of the signed URL. It is short on purpose and it
    | is not the same trade-off as the marketing unsubscribe token, which never
    | expires because an unsubscribe link in a two-year-old mail must still
    | work. Nothing in a two-year-old mail points here.
    |
    | `min_response_ms` is a floor under the response time of a link request, so
    | the fast path (no such address, nothing to do) cannot be told from the
    | slow one (address found, mail queued). Raise it if your mailer is slower
    | than this on the machine that serves the request.
    |
    */

    'magic_link' => [
        'enabled' => env('PREFERENCE_CENTER_MAGIC_LINK', true),
        'ttl_minutes' => env('PREFERENCE_CENTER_MAGIC_LINK_TTL', 30),
        'session_minutes' => env('PREFERENCE_CENTER_MAGIC_LINK_SESSION', 60),
        'min_response_ms' => env('PREFERENCE_CENTER_MAGIC_LINK_FLOOR_MS', 350),

        // Off, and it should stay off. On, this endpoint mails a signed link to
        // anything typed into it, which is an open relay with extra steps. It
        // exists for the one honest case: an installation with neither a
        // marketing list nor a contact store, where nobody is known yet.
        'allow_unknown_addresses' => env('PREFERENCE_CENTER_MAGIC_LINK_ALLOW_UNKNOWN', false),

        'throttle' => [
            // Per address: what stops one mailbox being flooded.
            'per_address' => [
                'max' => env('PREFERENCE_CENTER_THROTTLE_ADDRESS_MAX', 3),
                'decay_minutes' => env('PREFERENCE_CENTER_THROTTLE_ADDRESS_DECAY', 60),
            ],
            // Per origin: what stops this endpoint being used as an amplifier
            // to mail a list of addresses somebody else owns.
            'per_origin' => [
                'max' => env('PREFERENCE_CENTER_THROTTLE_ORIGIN_MAX', 10),
                'decay_minutes' => env('PREFERENCE_CENTER_THROTTLE_ORIGIN_DECAY', 60),
            ],
        ],

        'from' => [
            'address' => env('PREFERENCE_CENTER_MAIL_FROM'),
            'name' => env('PREFERENCE_CENTER_MAIL_FROM_NAME'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit
    |--------------------------------------------------------------------------
    |
    | Every applied change is announced as an event and written to a log with
    | the proof that authorised it. When LeadHub is installed the same record
    | also lands on the contact timeline, where a data-subject request will
    | look for it.
    |
    */

    'audit' => [
        'log_channel' => env('PREFERENCE_CENTER_AUDIT_LOG'),
        'leadhub' => env('PREFERENCE_CENTER_AUDIT_LEADHUB', true),
    ],

];
