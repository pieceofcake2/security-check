# CI with GitHub Actions

[docs/examples/security-check.yml](examples/security-check.yml) runs the three checks on each push and pull
request, and every week, with the test database that DAST needs, and uploads the three reports in one artifact:

```yaml
- run: composer install --no-interaction --no-progress
- run: sudo apt-get install -y fonts-noto-cjk
- run: vendor/bin/security-check
- uses: actions/upload-artifact@v7
  if: ${{ !cancelled() }}
  with:
    name: security-reports
    path: tmp/security/*.pdf
```

- The settings of the checks come from `config/security.php` ([The Settings File](README.md#the-settings-file)),
  e.g. `'report' => ['dir' => 'tmp/security']` for the directory of the reports.
- The weekly run (`schedule`) matters for SCA: new advisories are published for packages that did not change.
- DAST runs the application with `APP_ENV=test`: the job needs the test database with its tables, as for the
  tests (see [CI of security-dast](https://pieceofcake2.github.io/security-dast/ci)).
- `if: ${{ !cancelled() }}` uploads the reports also when a check fails the job, which is when they are needed.

## One Job or Three

One job with `security-check` is the simplest. Separate jobs, each with its own command, run in parallel and
show in the pull request which check failed:

```yaml
jobs:
  sca:
    runs-on: ubuntu-latest
    steps:
      # checkout, setup-php, composer install, fonts
      - run: vendor/bin/security-check --only=sca
  sast:
    runs-on: ubuntu-latest
    steps:
      - run: vendor/bin/security-check --only=sast
  dast:
    runs-on: ubuntu-latest
    # services: the test database
    steps:
      - run: vendor/bin/security-check --only=dast
```

The full scan of DAST on the End-to-End tests takes longer: run it every night in a workflow of its own (see
[security-dast-full.yml](https://github.com/pieceofcake2/security-dast/blob/1.x/docs/examples/security-dast-full.yml)).
