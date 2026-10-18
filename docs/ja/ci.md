# GitHub Actions での CI

[docs/examples/security-check.yml](../examples/security-check.yml) は、`main` への push と pull request のたびと毎週、
DAST に必要なテストのデータベースとともに 3 つの診断を実行し、3 つの報告書を 1 つの成果物としてアップロードします。

```yaml
- run: composer install --no-interaction --no-progress
- run: sudo apt-get install -y fonts-noto-cjk
- run: vendor/bin/security-check
- uses: actions/upload-artifact@v7
  if: ${{ !cancelled() }}
  with:
    name: security-reports
    path: |
      tmp/security/*.pdf
      tmp/security-*.pdf
    if-no-files-found: error
```

- 診断の設定は `config/security.php` から読みます ([設定ファイル](README.md#設定ファイル))。たとえば、報告書の
  ディレクトリは `'report' => ['dir' => 'tmp/security']` です。指定しないと、報告書は `tmp/security-sca.pdf`、
  `tmp/security-sast.pdf`、`tmp/security-dast.pdf` です。`path` はどちらも含みます。
- `if-no-files-found: error` で、報告書が 1 つもないときにアップロードを失敗させます。`path` が設定の
  ディレクトリと合っていないときなどです。
- 毎週の実行 (`schedule`) は SCA のためです。変えていないパッケージにも、新しいアドバイザリが公開されます。
- DAST はアプリケーションを `APP_ENV=test` で動かすので、ジョブには、テストと同じく、テーブルのあるテストの
  データベースが要ります ([security-dast の CI](https://pieceofcake2.github.io/security-dast/ja/ci))。
- `if: ${{ !cancelled() }}` で、診断がジョブを失敗させたときにも報告書をアップロードします。報告書が必要なのは
  そのときです。

## 1 つのジョブか 3 つか

`security-check` の 1 つのジョブが一番簡単です。診断ごとに分けたジョブは並行して動き、pull request でどの診断が
失敗したかがわかります。

```yaml
jobs:
  sca:
    runs-on: ubuntu-latest
    steps:
      # checkout、setup-php、composer install、フォント
      - run: vendor/bin/security-check --only=sca
  sast:
    runs-on: ubuntu-latest
    steps:
      - run: vendor/bin/security-check --only=sast
  dast:
    runs-on: ubuntu-latest
    # services: テストのデータベース
    steps:
      - run: vendor/bin/security-check --only=dast
```

End-to-End テストでの DAST のフルスキャンは時間がかかるので、専用のワークフローで毎晩実行してください
([security-dast-full.yml](https://github.com/pieceofcake2/security-dast/blob/1.x/docs/examples/security-dast-full.yml))。
