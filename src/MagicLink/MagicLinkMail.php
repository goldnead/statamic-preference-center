<?php

namespace Goldnead\PreferenceCenter\MagicLink;

use Illuminate\Mail\Mailable;

/**
 * The one mail this package sends.
 *
 * Deliberately plain and short. It goes to somebody who asked a question about
 * their own settings; anything that reads like a campaign in that moment is a
 * complaint waiting to happen.
 *
 * Two bodies, not one. A signed link is around three hundred characters, and
 * v1.0.0 sent it as `text/plain` only: a client that does not linkify wraps it,
 * a client that does linkify has to guess where it ends, and either way somebody
 * is asked to reassemble a URL by hand to reach a page about their own mail. The
 * text part stays — it is what renders where HTML is stripped — and the HTML
 * part carries the same URL as something to click.
 *
 * The two parts escape the URL differently and both are right. Each template
 * says why at the line that does it.
 */
class MagicLinkMail extends Mailable
{
    /**
     * @param  list<array{url:string, brand:?string}>  $links  one per brand that knows
     *                                                         the address; normally one
     */
    public function __construct(public array $links) {}

    /** The first link — what a single-brand install has exactly one of. */
    public function url(): string
    {
        return (string) ($this->links[0]['url'] ?? '');
    }

    public function build(): self
    {
        $from = (array) config('preference-center.magic_link.from', []);

        if (! empty($from['address'])) {
            $this->from($from['address'], $from['name'] ?: null);
        }

        return $this
            ->subject(__('preference-center::mail.magic_link_subject'))
            ->view('preference-center::mail.magic-link-html')
            ->text('preference-center::mail.magic-link')
            ->with([
                'links' => $this->links,
                'minutes' => (int) config('preference-center.magic_link.ttl_minutes', 30),
            ]);
    }
}
