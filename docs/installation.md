---
id: installation
title: Installation
sidebar_position: 2
description: Requirements and installation status for Dirthara Events.
---

## Requirements

PHP 8.5 or later within the PHP 8 series is required. Composer installs its one
runtime dependency, the PSR interface package it implements:

| Package | Provides |
| --- | --- |
| `psr/event-dispatcher` `^1.0` | The PSR-14 event dispatcher interfaces. |

## Package installation

Once published, install the package using Composer:

```sh
composer require dirthara/events
```

:::caution
There is no published release yet. The command above describes the intended
installation after publication.
:::

For development, follow the Docker and Composer setup in the repository's
[README](https://github.com/dirthara/events#readme). Development tooling
includes PHPUnit, Mago, and Xdebug.
