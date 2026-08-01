# Security policy

## Supported versions

Only the latest released version of this package receives fixes.

## Reporting a vulnerability

**Do not open a public issue.** Every URL this package serves is public and unauthenticated by
design — a magic-link door, a token door and a session door — so a report filed in the open is a
working exploit until it is patched.

Mail **info@adriangoldner.com** with:

- what you can reach, and from which of the three doors;
- the smallest request that demonstrates it;
- the version of this package and of `goldnead/statamic-brand-context`.

You will get an acknowledgement within three working days. Anything that lets one person read or
change another person's preferences, or that turns the magic-link request into an address-enumeration
oracle, is treated as urgent.

## Out of scope

Bugs in Statamic itself belong at [statamic/cms](https://github.com/statamic/cms/security). Rate
limits deliberately set low in your own `config/preference-center.php` are configuration, not a
vulnerability.
