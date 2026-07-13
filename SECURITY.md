# Security Policy

## Supported Versions

We actively provide security updates for the following versions:

| Version | Supported          |
| ------- | ------------------ |
| 1.4.x   | :white_check_mark: |
| 1.3.x   | :white_check_mark: |
| 1.2.x   | :x:                |
| 1.1.x   | :x:                |
| 1.0.x   | :x:                |

## Reporting a Vulnerability

We take the security of Secure Blog CMS seriously. If you believe you have found a security vulnerability, please do **not** report it via a public issue or discussion.

Instead, please report it through one of the following private channels:

1.  **Email**: Please send security reports to your contact email afterpacket(@)0xdeadbeef.email.
2.  **GitHub Private Disclosure**: Use the "Report a vulnerability" button under the "Security" tab of this repository if enabled.

### What to Include in Your Report

To help us address the issue quickly, please include:
- A descriptive title.
- The version(s) affected.
- A summary of the vulnerability and its potential impact.
- Step-by-step instructions to reproduce the issue (PoC).
- Any suggested fixes or mitigations.

## Our Disclosure Process

1.  **Acknowledgment**: We will acknowledge receipt of your report within 48-72 hours.
2.  **Evaluation**: We will investigate the report and determine its severity and impact.
3.  **Fix**: We will work on a patch to resolve the vulnerability.
4.  **Verification**: We will ask you to verify the fix if possible.
5.  **Release**: We will release a new version containing the security patch.
6.  **Advisory**: We will publish a security advisory alongside the release.

## Security Architecture

Secure Blog CMS is designed with a security-first approach:
- **SQL-Free Architecture**: Eliminates the risk of SQL Injection by using file-based JSON storage.
- **XSS Protection**: Multi-layer output escaping and input sanitization on all user-facing data, including DOM-based HTML purification.
- **CSRF Protection**: Unique, time-limited security tokens for all forms — single-use tokens prevent replay attacks.
- **Session Security**: Fingerprinting, periodic regeneration, and strict cookie flags (HttpOnly, Secure, SameSite).
- **Rate Limiting**: Native protection against brute-force attacks on login, comments, uploads, and short URLs.
- **Content Security Policy (CSP)**: Strict headers enabled by default to prevent unauthorized script execution and data exfiltration.
- **Path Sanitization**: Rigorous path validation to prevent directory traversal attacks.
- **Hashing**: Argon2id password hashing for the highest level of credential security.
- **Upgrade Security**: Manifest-only upgrades with mandatory SHA-256 checksums; auto-upgrade disabled by default.
- **Proxy Header Hardening**: Cloudflare/reverse proxy headers only trusted when explicitly enabled.

---

Thank you for helping keep Secure Blog CMS and its users safe!