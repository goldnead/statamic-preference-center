<?php

return [

    // Labels for the settings screen. The screen itself belongs to
    // statamic-brand-context; this addon supplies only the field list
    // (Support\Settings) and the words for it.

    'permission_manage_settings' => 'Manage preference centre settings',

    // The two answers a source switch has. No "on": whether a block appears
    // always also depends on whether its package is installed.
    'source_options' => [
        'auto' => 'Show it where the package is installed',
        'off' => 'Do not show it',
    ],

    'groups' => [

        'magic_link' => [
            'title' => 'Magic link',
            'description' => 'The third way onto the page, for somebody with no account and no old mail. The route prefix, the middleware and the master switch for the routes stay in config/preference-center.php: they are read while the routes are being registered, so a change made here would only take effect after the next deploy.',
        ],

        'throttle' => [
            'title' => 'Throttling',
            'description' => 'Requesting a magic link is public and unauthenticated by definition. Two counters keep it from becoming a tool: one protects a single mailbox, one stops this endpoint being used to mail somebody else\'s address list.',
        ],

        'sender' => [
            'title' => 'Sender',
            'description' => 'Who a magic link goes out as when the brand has no sender identity of its own. Where it has one, that wins.',
        ],

        'sources' => [
            'title' => 'Blocks on the page',
            'description' => 'Each block comes from a package that may or may not be installed. Off means: do not show it even where the package is installed. Turning a block on where the package is missing is not possible, because there would be nothing to ask. The marketing block is deliberately absent here and stays in config/preference-center.php: it decides whether the token route is registered at all, and that happens long before this screen.',
        ],

        'audit' => [
            'title' => 'Audit trail',
            'description' => 'Every applied change is announced as an event and written to a log with the proof that authorised it. Which log channel takes it stays in config/preference-center.php, because the channels belong to the host.',
        ],

    ],

    'fields' => [

        'magic_link_enabled' => [
            'label' => 'Offer magic links',
            'description' => 'Off, both /request addresses answer 404 and the page is only reachable through a marketing token or an authenticated session. Links already sent stop working.',
        ],
        'magic_link_ttl_minutes' => [
            'label' => 'Life of the link (minutes)',
            'description' => 'How long the signed URL can be opened after it was sent. Short on purpose: unlike the unsubscribe link in a marketing mail, nothing in a two-year-old mail points here. The number also appears in the text of the mail that carries the link.',
        ],
        'magic_link_session_minutes' => [
            'label' => 'Session after following the link (minutes)',
            'description' => 'How long somebody may stay on the page after clicking the link before they have to request a new one. Shortening it does not end a running session; it applies to the next one.',
        ],
        'magic_link_min_response_ms' => [
            'label' => 'Response time floor (ms)',
            'description' => 'A floor under the response time of a link request, so the fast path (no such address, nothing to do) cannot be told from the slow one (address found, mail queued). Without it the response time tells the caller who is on your list. Raise it if your mailer is slower than this on the machine that serves the request.',
        ],
        'magic_link_allow_unknown_addresses' => [
            'label' => 'Send links to unknown addresses too',
            'description' => 'Off, and it should stay off. On, this endpoint mails a signed link to anything typed into it, which is an open relay with extra steps. There is exactly one honest case for it: an installation with neither a mailing list nor a contact store, where nobody is known yet.',
        ],

        'magic_link_throttle_per_address_max' => [
            'label' => 'Requests per address',
            'description' => 'How many links the same address may request within the window below. The counter hangs on the address, not on the brand: a mailbox is one mailbox however many brands the host runs.',
        ],
        'magic_link_throttle_per_address_decay_minutes' => [
            'label' => 'Window per address (minutes)',
            'description' => 'How long a spent attempt counts. Changes apply to new attempts; a running block expires on its old window.',
        ],
        'magic_link_throttle_per_origin_max' => [
            'label' => 'Requests per origin',
            'description' => 'How often the same origin may request links within the window below, whatever the addresses. This is the counter that stops somebody pushing a list of addresses they do not own through here.',
        ],
        'magic_link_throttle_per_origin_decay_minutes' => [
            'label' => 'Window per origin (minutes)',
            'description' => 'How long a spent attempt per origin counts.',
        ],

        'magic_link_from_address' => [
            'label' => 'From address',
            'description' => 'Who the magic link mail comes from as long as the brand has no sender identity of its own. Empty takes the application\'s from address. An address your delivery service does not know leads to refused mail, not to an error here.',
        ],
        'magic_link_from_name' => [
            'label' => 'From name',
            'description' => 'The name beside the address. Empty sends the mail with the address alone.',
        ],

        'sources_notifications' => [
            'label' => 'Show product notifications',
            'description' => 'The notification matrix and the cadence block, from goldnead/statamic-notifications. Off removes the block from the page, and the write paths refuse it as firmly as the render path omits it: this is not a display trick a determined poster gets around.',
        ],
        'sources_suppression' => [
            'label' => 'Show blocks',
            'description' => 'The state from goldnead/statamic-suppression: what is blocked and why. Off, the page reports nothing as blocked even where something is. This is the one block whose absence makes the page dishonest.',
        ],
        'sources_sequences' => [
            'label' => 'Show sequences',
            'description' => 'The running automation sequences the person is in. Off, they cannot see them and cannot leave them here.',
        ],

        'audit_leadhub' => [
            'label' => 'Write changes to the CRM timeline',
            'description' => 'With goldnead/statamic-leadhub installed, every applied change also lands on the contact timeline, where a data-subject request will look for it. Off leaves only the log entry. Nothing is backfilled.',
        ],

    ],

];
