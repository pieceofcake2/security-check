# CHANGELOG

## 1.0.1

- An empty `--only` is an error, instead of running no check and passing
- Needs Composer 2.8+, as pieceofcake2/security-sca does
- The example workflow uploads the reports also without a settings file (`tmp/security-*.pdf`), and fails the
  upload when there is none
- Documentation: the settings file must only return its array, as each check reads it; a `null` is not set

## 1.0.0

- First release: `vendor/bin/security-check` runs the security checks of pieceofcake2 one after another,
  `security-sca`, `security-sast` and `security-dast`, each with its PDF report and its settings of
  `config/security.php`. `--only` chooses the checks, and the exit code is the highest of the checks
