---
id: installation
title: Installation
sidebar_position: 2
description: Requirements and installation of Dirthara Events.
---

## Requirements

PHP 8.5 or later within the PHP 8 series is required. Composer installs its one
runtime dependency, the PSR interface package it implements:

| Package | Provides |
| --- | --- |
| `psr/event-dispatcher` `^1.0` | The PSR-14 event dispatcher interfaces. |

The package declares that it provides `psr/event-dispatcher-implementation`, so a library that requires a PSR-14
implementation, rather than a specific dispatcher, can be installed together with it.

## Package installation

Install the package using Composer:

```sh
composer require dirthara/events
```

For development, follow the Docker and Composer setup in the repository's
[README](https://github.com/dirthara/events#readme). Development tooling
includes PHPUnit, Mago, and Xdebug.
