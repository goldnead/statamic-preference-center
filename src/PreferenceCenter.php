<?php

namespace Goldnead\PreferenceCenter;

use Goldnead\PreferenceCenter\Data\Access;
use Goldnead\PreferenceCenter\Data\PreferenceView;
use Goldnead\PreferenceCenter\Sources\MarketingSource;
use Goldnead\PreferenceCenter\Sources\NotificationsSource;
use Goldnead\PreferenceCenter\Sources\SuppressionSource;

/**
 * The page, assembled.
 *
 * Read order matters and is not an implementation detail: suppression is
 * resolved first, because its answer changes what the other two blocks are
 * allowed to show. A mailing list the gate has closed is not offered back, and
 * a notification channel that ends in a mailbox is shown off rather than shown
 * as the wish that will never be honoured.
 */
class PreferenceCenter
{
    public function __construct(
        public readonly MarketingSource $marketing,
        public readonly NotificationsSource $notifications,
        public readonly SuppressionSource $suppression,
    ) {}

    public function view(Access $access): PreferenceView
    {
        $suppression = $this->suppression->stateFor($access->email);

        $center = $this->marketing->centerFor($access);
        $lists = $this->marketing->available() ? $this->marketing->rows($center) : null;

        $types = $this->notifications->rows($access, $suppression);

        return new PreferenceView(
            access: $access,
            lists: $lists,
            types: $types,
            channels: $this->notifications->channels(),
            frequency: $this->notifications->frequency($access, $types),
            suppression: $suppression,
        );
    }

    /** The marketing DTO behind the list block, or null. Writes need it. */
    public function marketingCenter(Access $access): ?object
    {
        return $this->marketing->centerFor($access);
    }
}
