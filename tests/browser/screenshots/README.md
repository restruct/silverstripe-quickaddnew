# README screenshots

`readme.shot.ts` writes the PNGs in `docs/images/` that the module README shows. It is not a test
suite: it runs with its own config, `playwright.screenshots.config.ts` (in this directory), which
the browser tests and CI never load, so a normal test run never rewrites the images. Never edit the
PNGs by hand; regenerate them.

What it needs:

- a Silverstripe 6 host with this module installed and the browser fixtures from
  `tests/browser/fixtures/` copied into its `app/src/`, then the database built. The showcase
  fixture (`QanShowcase*.php`) adds an "Events" section to the CMS and seeds one event, "Spring open
  day", with a Venue dropdown, a Speakers listbox and a Topics checkbox set, each with Add New;
- `BROWSER_TARGET_URLS` pointing at that host (only the `ss6` target is used, else the first).

The shared runner in the maintainers' workspace does all of that. It starts Playwright in
`tests/browser/`, so the config path is relative to that directory:

```bash
BROWSER_ONLY=ss6 tools/browser/run.sh silverstripe-quickaddnew --config=screenshots/playwright.screenshots.config.ts
```

Against a host you started yourself, from `tests/browser/`:

```bash
npm ci
BROWSER_TARGET_URLS="ss6=http://127.0.0.1:8856" npx playwright test --config=screenshots/playwright.screenshots.config.ts
```

Every run adds a speaker named "Eva Jansen" through the dialog (that is what the after-add shot
shows). The seed data is only written while the "Spring open day" event does not exist, so to start
from clean demo content, delete that event and its venues, speakers and topics, then build the
database again.
