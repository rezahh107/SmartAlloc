# License Compliance

The project audits third-party dependencies using
`scripts/license-audit.php`. Each dependency from `composer.lock` is
checked against an approved list of SPDX license identifiers.

Approved Licenses
-----------------

The following licenses are pre-approved:

- MIT
- Apache-2.0
- GPL-2.0-or-later

Anything not in this list is reported as an advisory finding. Exceptions
may be granted by adding a temporary allowlist in release notes and
reviewing with the compliance team.

Updating the List
-----------------

To update the approved licenses, edit this file and add or remove SPDX
identifiers from the list above. Commit the change along with context in
the project changelog.

Running Locally
---------------

```sh
php scripts/license-audit.php
```

Results are written to `artifacts/compliance/license-audit.json` and the
script exits with code `0`.

