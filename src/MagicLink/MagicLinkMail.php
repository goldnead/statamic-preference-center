<?php

namespace Goldnead\PreferenceCenter\MagicLink;

use Illuminate\Mail\Mailable;

/**
 * The one mail this package sends.
 *
 * Deliberately plain and short. It goes to somebody who asked a question about
 * their own settings; anything that reads like a campaign in that moment is a
 * complaint waiting to happen.
 */
class MagicLinkMail extends Mailable
{
    public function __construct(public string $url) {}

    public function build(): self
    {
        $from = (array) config('preference-center.magic_link.from', []);

        if (! empty($from['address'])) {
            $this->from($from['address'], $from['name'] ?: null);
        }

        return $this
            ->subject(__('preference-center::mail.magic_link_subject'))
            ->text('preference-center::mail.magic-link')
            ->with([
                'url' => $this->url,
                'minutes' => (int) config('preference-center.magic_link.ttl_minutes', 30),
            ]);
    }
}
