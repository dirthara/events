# Security Policy

## Supported versions

| Version | Status |
| --- | --- |
| 0.1.x | Supported |
| Older | Unsupported |

While the package is pre-1.0, only the latest release line receives fixes.

## Reporting a vulnerability

Report vulnerabilities privately using GitHub's
[Report a vulnerability](https://github.com/dirthara/events/security/advisories/new)
form. Do not disclose vulnerabilities in public issues or pull requests.

Include the affected version or commit, PHP version, a minimal reproduction,
and the impact and conditions needed to trigger the issue. Maintainers will
acknowledge and assess the report. Confirmed fixes are published with an
advisory crediting the reporter unless they prefer otherwise.

## Scope

The package registers listeners, dispatches events to them synchronously, and publishes events as one-way
notifications, either straight away or deferred until an explicit flush in the same process. In scope are flaws in
that behaviour and in the package's development configuration, such as:

- an event type accepted that no object can have, or a listener called for an event it does not apply to;
- listeners called out of order, or after an event stopped propagation;
- an exception message or context letting a rejected value forge a log line;
- a deferred event lost, repeated, or reordered other than as the documentation describes.

Out of scope: this package has no queue, worker, asynchronous transport, event serialisation, retry infrastructure, or
external delivery guarantee. Security issues in those belong to the package or implementation that provides them.

Bugs in PHP or third-party dependencies should also be reported upstream.
Application code and the sensitivity of data an application chooses to store
are the application's responsibility.
