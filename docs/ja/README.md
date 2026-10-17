# Security Check ドキュメント

CakePHP 2 アプリケーションのセキュリティ診断を、1 つのコマンドで実行します。`vendor/bin/security-check` は、
pieceofcake2 の 3 つの診断を順に実行し、それぞれが A4 の PDF の報告書を書き出します。報告書は
[pieceofcake2/report](https://pieceofcake2.github.io/report/ja/) のデザインです。

| 診断 | パッケージ | 見るもの | 見つけるもの |
| --- | --- | --- | --- |
| SCA (ソフトウェア構成分析) | [pieceofcake2/security-sca](https://pieceofcake2.github.io/security-sca/ja/) | `composer.lock` の Composer のパッケージ | インストールしたバージョンの既知の脆弱性 (CVE)、開発が終了したパッケージ |
| SAST (静的アプリケーションセキュリティテスト) | [pieceofcake2/security-sast](https://pieceofcake2.github.io/security-sast/ja/) | コードと設定 | 文字列で組み立てた SQL、リクエストで選ぶビュー、リクエストの `unserialize()`、オープンリダイレクト、`h()` なしで出力する変数、既定の salt や鍵、本番のデバッグレベルなど |
| DAST (動的アプリケーションセキュリティテスト) | [pieceofcake2/security-dast](https://pieceofcake2.github.io/security-dast/ja/) | OWASP ZAP で、動いているアプリケーション | セキュリティヘッダーの不足、フラグのない Cookie、トークンのないフォーム、エラーページ。フルスキャンでは、攻撃で見つける XSS や SQL インジェクション |

- [GitHub Actions での CI](ci.md)

それぞれの診断は、ほかの診断では見つけられないものを見つけます。脆弱なパッケージはアプリケーションのコードにはなく、
セキュリティヘッダーもコードにはなく、攻撃はスキャンが見つけたページにしか届きません。3 つを組み合わせると、それぞれ
単独よりずっと広く調べられます。

## インストール

```bash
composer require --dev pieceofcake2/security-check
```

3 つのパッケージがインストールされます。それぞれの必要なものが必要です。PHP 8.0 以上、Composer 2.4 以上、Docker
(ZAP と、SAST で使うなら Semgrep のため)、PDF の印刷に Google Chrome か Chromium 131 以上、日本語の報告書には Linux
では `fonts-noto-cjk` です。

## 使い方

プロジェクトのディレクトリで実行します。

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

| オプション | 環境変数 | デフォルト | |
| --- | --- | --- | --- |
| `--project=DIR` | | カレントディレクトリ | プロジェクトのディレクトリ |
| `--settings=FILE` | `SECURITY_SETTINGS` | `config/security.php` か `app/Config/security.php` | 設定ファイル |
| `--only=LIST` | `SECURITY_CHECK_ONLY` | `sca,sast,dast` | 実行する診断。カンマ区切り |

終了コードは、診断のうち一番重いものです。どれかが実行できなかったら (Docker が動いていないなど) `2`、どれかが
`--fail-on` に達するものを見つけたら `1`、それ以外は `0` です。失敗した診断があっても、すべての診断を実行します。

報告書のパスは、単独で実行するときと同じく、カレントディレクトリからの相対パスです。プロジェクトで実行するか、絶対
パスを指定してください。

## 設定ファイル

それぞれの診断は、単独で実行するときと同じく、プロジェクトの設定ファイル `config/security.php` (従来の構成では
`app/Config/security.php`) の自分のセクションと、自分の環境変数を読みます。`report` セクションは共通です。

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

キーは、それぞれのコマンドのオプションをキャメルケースにしたものです (`--fail-on` は `failOn`)。それぞれの設定は
[SCA](https://pieceofcake2.github.io/security-sca/ja/#設定ファイル)、
[SAST](https://pieceofcake2.github.io/security-sast/ja/#設定ファイル)、
[DAST](https://pieceofcake2.github.io/security-dast/ja/configuration) をご覧ください。

## 報告書から安全なアプリケーションへ

既存のアプリケーションでは、次の順に報告書を進めてください。

1. **SCA: パッケージを更新します。** 一番早く修正でき、たいていは一番急ぎます。既知の脆弱性は詳細が公開されています。
   [脆弱性を修正する](https://pieceofcake2.github.io/security-sca/ja/fixing)
2. **SAST: 本番の設定。** デバッグレベル、salt、Cookie の鍵は、いくつかの設定です。秘密の値を変える前に、変えると何が
   使えなくなるかを読んでください。[設定](https://pieceofcake2.github.io/security-sast/ja/rules#設定)
3. **DAST: セキュリティヘッダーと Cookie。** ベースラインスキャンのアラートのほとんどは、`AppController` と Web
   サーバーで、アプリケーション全体に一度で修正できます。[アラート](https://pieceofcake2.github.io/security-dast/ja/alerts)
4. **SAST: コード。** 高の指摘 (SQL、ビュー、`unserialize()`) から、次に中の指摘を修正します。
   [指摘を修正する](https://pieceofcake2.github.io/security-sast/ja/fixing)
5. **DAST: フルスキャン。** ユーザーとして、End-to-End テストでフルスキャンします。修正が保たれていることを攻撃で
   確かめます。[End-to-End テストをシナリオにする](https://pieceofcake2.github.io/security-dast/ja/end-to-end)

そのあと CI で保ちます。各診断を `failOn` を `none` にして始め、報告書に何もなくなったら上げてください。脆弱性を
足した pull request が失敗するようになります ([CI](ci.md))。
