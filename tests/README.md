# Offline payment regression checks

Run from the plugin directory with PHP 7.4 or newer:

```sh
php tests/regression.php
```

No WordPress install or Composer dependency is required. The harness loads the actual plugin settings, signature, API-client, booking, REST, and logger classes. Small WordPress stubs capture registered hooks/routes, database mutations, and redirects; a redirect exception prevents the production `exit` from ending the runner. HTTP requests throw immediately. All credentials, IDs, domains, payment values, and log contents are synthetic. Logger output is restricted to a unique temporary directory and cleaned up afterward.

The checks cover:

- Success, cancellation, failure, unexpected/empty codes, missing and malformed query values, and WordPress-slash handling.
- No browser-return deletion, trashing, metadata writes, or payment-state events, including when the gateway setting is disabled or a destination is blank.
- Actual validated success/failure callback execution in live and sandbox modes, with logging both enabled and disabled, including acknowledgement and integration hook payloads.
- Rejection of invalid signatures, currency, amount, and environment without a payment-state hook.

To prove the regression against a separate checkout or extracted archive without replacing working files:

```sh
php tests/regression.php --source-root=/path/to/baseline-plugin
```

The baseline containing the unsafe return handler and missing `GPG_Logger::info()` should fail. These focused offline tests do not replace a WordPress staging checkout, provider sandbox integration test, or a full security review. They do not contact Geidea or a live site.
