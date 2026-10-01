# Strict Publication Baseline

Publication is always enforced.

There is no user-facing observe/migration switch in the final baseline.

```text
Super-admin
  -> all available facilities
  -> no publication grant required

Normal user
  -> Today/core shell
  -> explicitly published workflows
  -> automatic dependency closure
  -> direct URL to unpublished mapped workflow = 403
```

`release.profile` no longer controls route reachability.

`PlatformCapability` remains an action-level authorization concept and does
not decide which application facilities are revealed.

The canonical application layout is `resources/views/layouts/app.blade.php`,
used through classic Blade inheritance (`@extends('layouts.app')`), not
`<x-layouts.app>`.
