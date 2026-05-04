# Security Policy

## Supported Versions

Only the latest stable release receives security patches.

| Version | Supported |
|---------|-----------|
| 1.x (latest) | ✅ Yes |
| < 1.0 | ❌ No |

---

## Reporting a Vulnerability

**Please do not open a public GitHub issue for security vulnerabilities.**

We ask that you follow responsible disclosure practices and report security issues privately so we can prepare a fix before public disclosure.

### How to report

Send an email to **shamavurasheed@gmail.com** with:

- **Subject line:** `[SECURITY] php-io-cli — <brief description>`
- A clear description of the vulnerability
- Steps to reproduce (proof-of-concept code is welcome)
- The potential impact in your assessment
- The version(s) affected

We use PGP-encrypted email if you prefer — ask for our public key in a separate (non-sensitive) message first.

### What to expect

| Timeline | Action |
|----------|--------|
| **Within 48 hours** | We acknowledge receipt of your report |
| **Within 7 days** | We assess severity and confirm whether we can reproduce |
| **Within 30 days** | We aim to release a patch (complex issues may take longer) |
| **After the patch is released** | We publicly credit the reporter (unless you prefer anonymity) |

If we cannot reproduce the issue or determine it to be out of scope, we will explain why.

---

## Scope

### In scope

- Code execution vulnerabilities in the library itself
- Unintended information disclosure via `Shell::run()`, `ConsoleIO`, or `BufferIO`
- Escape-sequence injection that could hijack a host terminal session
- Dependency vulnerabilities that affect `php-io-cli` users when installed as a library

### Out of scope

- Vulnerabilities in downstream applications that happen to use this library
- Issues that require physical access to the machine running the CLI
- Social engineering attacks
- Bugs without a security impact (please open a regular issue instead)

---

## Security considerations for users

### Shell::run()

`Shell::run()` executes arbitrary shell commands via `proc_open`. **Never pass unsanitised user input as the `$command` argument.** Always construct commands from trusted, fixed strings, and validate any user-supplied values before interpolating them.

```php
// ❌ Unsafe — user controls $branch
Shell::run("git checkout {$branch}");

// ✅ Safe — validate before use
if (!preg_match('/^[a-zA-Z0-9._\-\/]+$/', $branch)) {
    throw new \InvalidArgumentException('Invalid branch name');
}
Shell::run('git checkout ' . escapeshellarg($branch));
```

### Terminal raw mode

`Terminal::enableRaw()` disables canonical input processing and echo. The library registers a shutdown function and signal handlers to restore the terminal on exit. If your application forks or spawns child processes while a component is running, ensure child processes do not inherit the raw-mode state of the parent.

### BufferIO in production

`BufferIO` is designed for testing. Do not use it in production environments, as it writes everything to an in-memory `php://memory` stream and may buffer sensitive data (passwords, tokens) in process memory longer than necessary.

---

## Acknowledgements

We are grateful to the security researchers and community members who help keep this project safe. Confirmed reporters will be listed here (with permission) after the relevant patch is released.
