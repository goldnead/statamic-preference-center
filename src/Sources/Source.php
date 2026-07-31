<?php

namespace Goldnead\PreferenceCenter\Sources;

/**
 * A block of the page whose package may or may not be installed.
 *
 * Presence is decided by asking the class map, never by reading a composer
 * manifest: the three sources are `suggest`, not `require`, and a host running
 * a path repository, a fork or a `replace` still has the classes.
 *
 * The config key is a switch, not an override — `false` turns a block off even
 * where the package is installed (which is how the absent case is exercised in
 * a suite that happens to have all three), and nothing turns a block on where
 * the classes are missing, because there would be nothing to call.
 */
abstract class Source
{
    /** The config key under `preference-center.sources`. */
    abstract public function key(): string;

    /**
     * The class whose presence means this source is installed.
     *
     * A method rather than a constant so a test can subclass and point it at a
     * name that genuinely does not exist — otherwise the `class_exists` branch
     * is the one line of this package that no test could reach.
     */
    abstract protected function marker(): string;

    public function available(): bool
    {
        return $this->enabled() && $this->installed();
    }

    public function enabled(): bool
    {
        $configured = config('preference-center.sources.'.$this->key(), 'auto');

        return ! in_array($configured, [false, 'false', '0', 0, 'off'], true);
    }

    public function installed(): bool
    {
        // `interface_exists` as well as `class_exists`: the marker for
        // suppression is its `Gate` contract, and `class_exists` answers false
        // for an interface. Getting that wrong does not throw — it silently
        // decides the package is absent and renders a page with no blocks on it.
        return class_exists($this->marker()) || interface_exists($this->marker());
    }
}
