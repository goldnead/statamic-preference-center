<?php

/*
 * This page is read by somebody who just wanted to turn something off. Short,
 * factual, no marketing and no win-back attempts.
 */

return [

    'title' => 'Email preferences',
    'intro' => 'These settings apply to :email.',

    'lists_heading' => 'Mailing lists',
    'lists_hint' => 'Editorial email. It rests on your consent and can be ended at any time.',
    'lists_empty' => 'There is currently no mailing list set up for this address.',
    'lists_blocked' => 'Sending to this address is blocked. It cannot be switched on here.',

    'types_heading' => 'Product notifications',
    'types_hint' => 'Notices from the product, by kind and by route.',
    'types_empty' => 'This installation has no notification types.',
    'types_column' => 'Kind',
    'types_required' => 'Required: account security and legal notices cannot be switched off.',

    'channel_in_app' => 'In product',
    'channel_mail' => 'Email',
    'channel_digest' => 'Digest',

    'frequency_heading' => 'Frequency',
    'frequency_hint' => 'Applies to every switchable notification below. Changing it here rewrites their email and digest settings.',
    'frequency_mixed' => 'Currently mixed: some notifications arrive as they happen, others collected. Pick one option to make it uniform.',
    'frequency_immediate' => 'Immediately',
    'frequency_immediate_desc' => 'Each notification arrives on its own, as it happens.',
    'frequency_daily' => 'Daily',
    'frequency_daily_desc' => 'Collected once a day.',
    'frequency_weekly' => 'Weekly',
    'frequency_weekly_desc' => 'Collected once a week.',
    'frequency_never' => 'Never',
    'frequency_never_desc' => 'No email about product notifications. You still see them in the product.',

    'suppression_blocked' => 'This address is not being delivered to at the moment. The reason lies with the mailbox itself, such as a bounce or a complaint. That block cannot be lifted here; you can still unsubscribe.',
    'suppression_unavailable' => 'The block status of this address cannot be queried right now. While that is so, nothing that might be blocked is switched back on.',

    'save' => 'Save preferences',
    'saved' => '{0} Nothing was changed.|{1} One change saved.|[2,*] :count changes saved.',

    'all_heading' => 'Unsubscribe from everything',
    'all_body' => 'Ends every mailing list of this brand at once. Email that does not rest on consent, such as an order confirmation, is not affected.',
    'all_button' => 'Unsubscribe from all lists',
    'all_done' => '{0} Nothing was active.|{1} One list was ended.|[2,*] :count lists were ended.',

    'refused_blocked' => 'Blocked. This setting was not changed.',
    'refused_required' => 'Required. This notification cannot be switched off.',
    'refused_unidentified' => 'There is no account and no contact for this address yet, so notification settings cannot be stored here.',
    'refused_unknown' => 'One of the selected settings does not exist and was ignored.',
    'refused_source_absent' => 'That section is not set up on this installation.',

    'nothing_installed' => 'There is nothing to set for this address here.',

    'proof_unsubscribe_token' => 'You are here through the link in an email. Every change is recorded with that proof.',
    'proof_magic_link' => 'You are here through a link you requested. Every change is recorded with that proof.',
    'proof_session' => 'You are signed in. Every change is recorded with that proof.',

    'request_title' => 'Open your preferences',
    'request_intro' => 'Enter your address. If we can reach it, we will send a link to your preferences.',
    'request_label' => 'Email address',
    'request_placeholder' => 'name@example.com',
    'request_button' => 'Send the link',
    'request_foot' => 'The link is valid for :minutes minutes and leads only to these preferences.',
    'magic_link_sent' => 'If we can reach this address, a link is on its way. For privacy reasons we do not say here whether an address is known to us.',

    'sequences_heading' => 'Series',
    'sequences_hint' => 'Multi-part email series. You can leave one without unsubscribing from anything else.',
    'sequences_left' => 'stopped',
];
