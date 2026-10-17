# CHANGELOG

## Unreleased

- First release: `vendor/bin/security-check` runs the security checks of pieceofcake2 one after another,
  `security-sca`, `security-sast` and `security-dast`, each with its PDF report and its settings of
  `config/security.php`. `--only` chooses the checks, and the exit code is the highest of the checks
