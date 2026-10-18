# pieceofcake2/security-check

[![GitHub License](https://img.shields.io/github/license/pieceofcake2/security-check?label=License)](LICENSE)
[![Packagist Version](https://img.shields.io/packagist/v/pieceofcake2/security-check?label=Packagist)](https://packagist.org/packages/pieceofcake2/security-check)
![PHP](https://img.shields.io/packagist/dependency-v/pieceofcake2/security-check/php?logo=php&logoColor=%23FFFFFF&label=PHP&labelColor=%23777BB4&color=%23FFFFFF)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%205-brightgreen?label=PHPStan)](https://github.com/pieceofcake2/security-check/actions/workflows/CI.yml)
[![CI](https://img.shields.io/github/actions/workflow/status/pieceofcake2/security-check/CI.yml?label=CI)](https://github.com/pieceofcake2/security-check/actions/workflows/CI.yml)
[![Codecov](https://img.shields.io/codecov/c/gh/pieceofcake2/security-check?label=Coverage)](https://codecov.io/gh/pieceofcake2/security-check)

The security checks of a CakePHP 2 application in one command: the known vulnerabilities of its Composer packages
([pieceofcake2/security-sca](https://github.com/pieceofcake2/security-sca)), its code and configuration
([pieceofcake2/security-sast](https://github.com/pieceofcake2/security-sast)), and the running application with
OWASP ZAP ([pieceofcake2/security-dast](https://github.com/pieceofcake2/security-dast)), each as an A4 PDF report
in the design of [pieceofcake2/report](https://github.com/pieceofcake2/report), with an exit code that fails CI.

```bash
composer require --dev pieceofcake2/security-check
vendor/bin/security-check
```

## Documentation

- Website: https://pieceofcake2.github.io/security-check/ (日本語: https://pieceofcake2.github.io/security-check/ja/)
- Markdown: [docs/README.md](docs/README.md) (日本語: [docs/ja/README.md](docs/ja/README.md)) (for AI tools and offline reading)

## Requirements

* PHP 8.0+ and Composer 2.8+
* Docker (for ZAP, and for Semgrep when SAST uses it)
* Google Chrome or Chromium 131+

## Installation

```bash
composer require --dev pieceofcake2/security-check
```

## Reporting Issues

Please open an issue on [GitHub](https://github.com/pieceofcake2/security-check/issues).
To report a security vulnerability, use [private vulnerability reporting](https://github.com/pieceofcake2/security-check/security/advisories/new).

## Contributing

See the [contribution guidelines](https://github.com/pieceofcake2/.github/blob/master/CONTRIBUTING.md).

## Origin

This package was created by [pieceofcake2](https://github.com/pieceofcake2), which also maintains it. It is
not a fork of another project and is not based on one.

## License

The MIT License. See [LICENSE](LICENSE).
