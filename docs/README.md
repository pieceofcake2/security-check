# Security Check Documentation

The security checks of a CakePHP 2 application in one command. `vendor/bin/security-check` runs the three checks
of pieceofcake2 one after another, and each writes its A4 PDF report, in the design of
[pieceofcake2/report](https://pieceofcake2.github.io/report/):

| Check | Package | Looks at | Finds |
| --- | --- | --- | --- |
| SCA (software composition analysis) | [pieceofcake2/security-sca](https://pieceofcake2.github.io/security-sca/) | The Composer packages of `composer.lock` | Known vulnerabilities (CVE) of the installed versions, abandoned packages |
| SAST (static application security testing) | [pieceofcake2/security-sast](https://pieceofcake2.github.io/security-sast/) | The code and the configuration | SQL built from strings, views rendered from the request, `unserialize()` of the request, open redirects, variables printed without `h()`, the default salts and keys, a debug level of production, ... |
| DAST (dynamic application security testing) | [pieceofcake2/security-dast](https://pieceofcake2.github.io/security-dast/) | The running application, with OWASP ZAP | Missing security headers, cookies without flags, forms without a token, error pages; with the full scan, XSS and SQL injections found by attacks |

- [CI with GitHub Actions](ci.md)

Each check finds what the others cannot: a vulnerable package is in no code of the application, a security
header is in no code either, and an attack only reaches the pages that a scan finds. The three together cover
much more than each of them.

## Installation

```bash
composer require --dev pieceofcake2/security-check
```

It installs the three packages. Their requirements apply: PHP 8.0+, Composer 2.8+, Docker (for ZAP, and for
Semgrep when SAST uses it), Google Chrome or Chromium 131+ to print the PDFs, and on Linux `fonts-noto-cjk` for
Japanese reports.

## Usage

Run it in the directory of the project:

```bash
vendor/bin/security-check
```

```text
security-sca
0 vulnerabilities in 71 packages, 0 abandoned packages. Report: tmp/security/security-sca.pdf
security-sast
17 findings (1 high, 15 medium, 1 low) of 3 rules in 26 files (PHPStan 2.3.1). Report: tmp/security/security-sast.pdf
security-dast
2 alerts (2 low) on 6 URLs of http://127.0.0.1:49560 (ZAP 2.17.0). Report: tmp/security/security-dast.pdf
Security checks: sca passed, sast failed, dast passed.
```

| Option | Environment variable | Default | |
| --- | --- | --- | --- |
| `--project=DIR` | | the current directory | The directory of the project |
| `--settings=FILE` | `SECURITY_SETTINGS` | `config/security.php` or `app/Config/security.php` | The settings file |
| `--only=LIST` | `SECURITY_CHECK_ONLY` | `sca,sast,dast` | The checks to run, comma separated |

The exit code is the highest of the checks: `2` when one could not run (e.g. Docker is not running), `1` when one
found what reaches its `--fail-on`, `0` otherwise. Every check runs, also after one that failed.

The paths of the reports are relative to the current directory, as when the checks run alone: run the command
in the project, or give absolute paths.

## The Settings File

Each check reads its section of the settings file of the project, `config/security.php`
(`app/Config/security.php` in the legacy layout), and its environment variables, as when it runs alone. The
`report` section is shared:

```php
<?php
// config/security.php
return [
    'report' => [
        'language' => 'ja',
        'dir' => 'tmp/security',
        'project' => 'Acme',
    ],
    'sca' => [
        'failOn' => 'high',
        'noDev' => false,
    ],
    'sast' => [
        'engine' => 'semgrep',
        'failOn' => 'high',
        'safeViewVariables' => ['body_html'],
    ],
    'dast' => [
        'failOn' => 'medium',
        'loginUrl' => '/users/login',
        'username' => 'test@example.com',
        'password' => getenv('SECURITY_DAST_PASSWORD') ?: null,
    ],
];
```

The file is read by each check in turn, so it must only return the array: a function or a class declared in it
would be declared again by the second check, which fails. A value that is `null` is not set, e.g. the password
when `SECURITY_DAST_PASSWORD` is not set.

The keys are the options of each command in camel case (`--fail-on` is `failOn`). See the settings of each check:
[SCA](https://pieceofcake2.github.io/security-sca/#the-settings-file),
[SAST](https://pieceofcake2.github.io/security-sast/#the-settings-file) and
[DAST](https://pieceofcake2.github.io/security-dast/configuration).

## From the Reports to a Secure Application

For an existing application, go through the reports in this order:

1. **SCA: update the packages.** It is the quickest fix and often the most urgent: a known vulnerability has
   public details. [Fixing a Vulnerability](https://pieceofcake2.github.io/security-sca/fixing)
2. **SAST: the configuration of production.** The debug level, the salt and the keys of the cookies are a few
   settings; read what a change of a secret breaks before changing it.
   [The Configuration](https://pieceofcake2.github.io/security-sast/rules#the-configuration)
3. **DAST: the security headers and the cookies.** Most alerts of the baseline scan are fixed once for the whole
   application, in `AppController` and the web server. [Alerts](https://pieceofcake2.github.io/security-dast/alerts)
4. **SAST: the code**, from the High findings: SQL, views, `unserialize()`, then the Medium ones.
   [Fixing the Findings](https://pieceofcake2.github.io/security-sast/fixing)
5. **DAST: the full scan** on the End-to-End tests, as a user: it checks by attacks that the fixes hold.
   [End-to-End Tests as the Scenario](https://pieceofcake2.github.io/security-dast/end-to-end)

Then keep it secure in CI: start each check with `failOn` set to `none`, and raise it once its report is clean,
so that a pull request that adds a vulnerability fails ([CI](ci.md)).
