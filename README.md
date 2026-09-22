# Lighthouse Insight

TYPO3 backend module that runs Google PageSpeed Insights (or a local Lighthouse CLI) analyses against TYPO3 pages and shows the results — scores, lab/field metrics, and opportunity/diagnostic findings — directly in the backend, color-coded to Lighthouse's own good/average/poor thresholds. The URL to analyze is either resolved automatically from the selected page or overridden manually via a TYPO3 link field (page or external URL). The backend UI is available in English and German.

## Requirements

- TYPO3 14.3+
- PHP 8.2+
- Composer installation
- Google PageSpeed Insights API key (only needed for the `Google PageSpeed API` engine; the `Local Lighthouse` engine needs no API key)

## Installation

Add the package to a Composer TYPO3 project:

```bash
composer require anubit/typo3-lighthouse-insight
```

For local path development inside this repository, require it as:

```bash
composer require anubit/typo3-lighthouse-insight:@dev
```

Then activate the extension if your installation does not auto-activate Composer extensions:

```bash
vendor/bin/typo3 extension:setup lighthouse_insight
```

The backend module appears under `Content > PageSpeed` for backend users with access to that module.

## Configuration

All settings are in the extension configuration (Admin Tools > Settings > Extension Configuration > lighthouse_insight), or directly in `config/system/settings.php` / `AdditionalConfiguration.php`:

| Key | Tab | Type | Description |
| --- | --- | --- | --- |
| `apiKey` | Basic | string | Google PageSpeed Insights API key. Required for the `Google PageSpeed API` engine. |
| `localChromeFlags` | local | string | Chrome flags used by the local Lighthouse CLI run. Defaults to `--headless --ignore-certificate-errors --no-sandbox`. |
| `localLighthousePackage` | local | string | npm package/version run via `npx`. Defaults to `lighthouse@10.4.0`. |
| `localTimeout` | local | int | Timeout in seconds for the local Lighthouse process. Defaults to `120`. |

Set the API key via extension configuration (never commit a real key to a versioned config file):

```php
$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['lighthouse_insight']['apiKey'] = getenv('PAGESPEED_API_KEY') ?: '';
```

For local Lighthouse analysis, the PHP runtime must be able to execute:

```bash
npx --yes lighthouse@10.4.0 --version
```

In DDEV this means Node, npm, and Chrome/Chromium must be available in the web container, not only on the host machine. This repository includes a DDEV web image addition that installs Chromium after `ddev restart`.

The backend module checks these local requirements and shows a warning with setup tasks when Local Lighthouse is unavailable.

No configuration is needed for the German backend labels — TYPO3 picks `de.locallang.xlf` automatically for backend users whose interface language is set to German.

## Obtaining an API Key

1. Open Google Cloud Console.
2. Create or choose a project.
3. Enable the PageSpeed Insights API.
4. Create an API key.
5. Restrict the key according to your deployment policy.
6. Store it in the TYPO3 environment/system configuration.

## Backend Usage

1. Open the TYPO3 backend.
2. Select a page in the page tree.
3. Open `Content > PageSpeed`.
4. Review the resolved frontend URL, or override it: type a URL directly, or click the link icon to pick a page or external URL via TYPO3's native link browser. Leave it empty to keep using the page's own resolved URL.
5. Choose the engine (`Google PageSpeed API` or `Local Lighthouse`) and strategy (Mobile or Desktop).
6. Click `Run analysis`.

The module sends the resolved (or manually overridden) URL to the selected engine and renders the Lighthouse scores, lab data, field data, and findings in TYPO3. Scores and field-data badges are color-coded to Lighthouse's own thresholds: green (≥90 / FAST), orange (50–89 / AVERAGE), red (<50 / SLOW).

Local DDEV URLs such as `*.ddev.site` cannot be analyzed by Google PageSpeed Insights because Google must be able to reach the URL from outside your machine. Use a public staging URL or expose the local site through a temporary tunnel when testing development instances.

Alternatively choose the `Local Lighthouse` engine. It runs Lighthouse locally via `npx --yes lighthouse@10.4.0` and can analyze DDEV URLs, provided the TYPO3 PHP environment has Node and Chrome/Chromium available.

## Architecture

- `PageSpeedController`: thin backend controller; also resolves the manual URL override (page link or external URL) via `LinkService` and TYPO3's Link Browser.
- `PageUrlResolver`: resolves TYPO3 page UIDs to absolute frontend URLs via Site Handling and routing.
- `PageSpeedService`: server-side HTTP communication with Google PageSpeed Insights.
- `LocalLighthouseService`: local Lighthouse CLI runner for development/internal URLs.
- `PageSpeedResponseParser`: maps Google/Lighthouse JSON into small DTOs, including the good/average/poor score rating per category.
- DTOs: `PageSpeedResult`, `LighthouseMetric`, `FieldMetric`, `LighthouseAudit`.
- `UrlSecurityValidator`: validates URLs before analysis (Google API engine only).
- `Translator`: resolves backend labels via TYPO3's `LanguageService` (`locallang.xlf` / `de.locallang.xlf`).

No raw Lighthouse JSON is passed to Fluid.

## Security

- API key stays server-side.
- Module access uses TYPO3 backend module permissions.
- The analyzed URL is either resolved from the selected page, or a manual override restricted to TYPO3 pages or external URLs (picked via TYPO3's own Link Browser, or typed directly) — no arbitrary link types (file/folder/email/record) are accepted.
- URL validator rejects local/private/reserved targets before the Google API service call.
- Local Lighthouse accepts any resolved/overridden URL and runs Chrome/Lighthouse on the TYPO3 server itself — enable it only in trusted development environments.
- Technical errors are logged; users see safe, localized backend messages.

The Google API engine does not run Chrome or Lighthouse on the TYPO3 server. The local engine does, and should be enabled only in trusted development environments.

## Field Data vs Lab Data

Lighthouse lab metrics are shown separately from Chrome UX Report field data.

INP is shown only when Google provides field data. TBT is kept as a Lighthouse lab metric and is not presented as INP.

Local Lighthouse reports lab data only. Chrome UX Report field data is only available through Google PageSpeed Insights.

## Development

Run PHP syntax checks from the project root:

```bash
find packages/lighthouse_insight -name '*.php' -print0 | xargs -0 -n1 php -l
```

Run unit tests from the extension directory after installing dev dependencies:

```bash
composer install
vendor/bin/phpunit
```