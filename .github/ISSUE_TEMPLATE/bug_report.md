---
name: Bug report
about: Create a report to help us improve
title: "[BUG]: "
labels: bug
assignees: ''

---

Questions? Discussions: https://phalcon.io/discussions or Discord: https://phalcon.io/discord

**Describe the bug**
A clear and concise description of what the bug is.

**To Reproduce**
Steps to reproduce the behavior. Include the exact command you ran:

```bash
vendor/bin/crest <command> <arguments>
```

> Re-run with `--trace` and paste the full output. That turns the one-line error
> into a stack trace, which is usually the whole answer.

```
paste output here
```

**Expected behavior**
A clear and concise description of what you expected to happen.

**Generated output**
If the bug is in a generator, paste the file crest produced and describe how it
differs from what you expected.

```php
// paste generated code
```

**Details**
Paste the output of `vendor/bin/crest about` — it reports the PHP, Phalcon and
crest versions in one go:

```
paste `crest about` output here
```

 - Phalcon variant: ext-phalcon (`^5`) | phalcon/phalcon (`^6`)
 - Operating System:
 - Installation type: `composer require --dev phalcon/crest` | from source
 - Project flavor: adr | mvc | cli
 - `crest.php` present: yes | no (if yes, paste it)

**Additional context**
Add any other context about the problem here.
