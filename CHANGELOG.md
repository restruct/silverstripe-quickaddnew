# Changelog

## 2.1.1

Fixes a regression introduced in 2.1.0. Upgrade from 2.1.0 is strongly recommended; 2.0.3 and
earlier are unaffected.

### Fixed

- **2.1.0 broke DB-touching tests in consuming projects.** This module's recursion-guard fixtures
  shared an `abstract` TestOnly `DataObject` base. `TableBuilder::buildTables()` instantiates every
  manifest class (`new $dataClass([], DataObject::CREATE_SINGLETON)`, `TableBuilder.php:28`) BEFORE
  it checks `instanceof TestOnly`, so an abstract DataObject subclass anywhere in the manifest
  fatals the temp-database build with "Cannot instantiate abstract class". In test mode
  `ignore_tests` is false, so an installed module's test fixtures are in the manifest.

  Every DB-touching test in the consuming project errored. **Production and `dev/build` were NOT
  affected** - `ManifestFileFinder` defaults `ignore_tests => true` outside test mode, so vendor
  `tests/` dirs stay out of the normal manifest. The failure also only appeared on the next flush,
  not at update time, so a consumer could update, see green, and break later.

  Fixed by dropping the shared abstract base; each fixture now extends `DataObject` directly and
  repeats `canCreate()`. The duplication is deliberate and commented as such.

  Reported by the FUSE project after updating. Note that this module's own suite could not have
  caught it: running a module's tests directly loads the fixtures through PHPUnit rather than
  registering them in the class manifest, so no tables are built and the abstract class is never
  instantiated. Verified instead by reproducing the consumer shape - a `$usesDatabase = true` test
  in the harness project, run with `flush=1`, which fails with the abstract base present and passes
  without it.

## 2.1.0

Pulls in the upstream (`sheadawson/quickaddnew` 2.0.0) work this fork had diverged from, and hardens
the recursion guard. No API changes.

### Added

- **`CheckboxSetField` support** (upstream). The README has described this field as supported since
  long before `_config/quickaddnew.yml` actually registered it; it is now registered, and the
  dialog's submit handler sends the existing checked values.
- **`SearchableDropdownField` styling** (upstream) - the SS5 field previously had no width rules in
  a quickaddnew context.
- French translations, `lang/fr.yml` and `client/javascript/lang/fr.js` (upstream).
- `.github/FUNDING.yml`, and a sponsorship line in the README.

### Fixed

- **The recursion guard no longer disables unrelated nested fields, and survives an exception.**
  `useAddNew()` used a single process-global boolean (`$is_creating`), so *any* nested
  `useAddNew()` was skipped - not just the genuine cycle it was written for (a class whose add-new
  form contains a field on itself). Worse, the flag was cleared by a plain assignment, so an
  exception thrown while building the fields left it stuck at `true` and silently disabled
  quickaddnew for every later field in the request.

  Replaced with a per-class stack popped in a `finally` block: a class that references itself is
  still blocked, a nested *different* class now works, and a throw unwinds cleanly.
- Dialog tab content no longer collapses behind floated elements (upstream `clear: both`).
- Docblock typo, `fucntionality` -> `functionality` (upstream).

### Not taken from upstream

The package rename to `sheadawson/quickaddnew` and the matching `Requirements` paths (ours are
correct for this fork), the removal of `sheadawson/quickaddnew` from `replace`, upstream's deletion
of the `tests/` folder, and upstream's `ci.yml`.

Note that **upstream 2.0.0 does not contain the 2.0.3 Injector-singleton fix** - it is affected by
that defect.

## 2.0.3

Bugfix release. No API changes; upgrade is a straight `composer update`.

### Fixed

- **Every "Add New" dialog opened the last-registered class's form, and could write the wrong
  class to the database.** `QuickAddNewExtension` stores per-field state on itself
  (`$addNewClass`, `$addNewFields`, `$sourceCallback`, `$addNewRequiredFields`, `$isFrontend`),
  but since SS4 `Extensible::getExtensionInstances()` resolves extensions through
  `Injector::inst()->get()`, which treats an unregistered class as a **singleton**. Every
  `DropdownField` and `ListboxField` in a request therefore shared one extension instance, and the
  last `useAddNew()` call overwrote the state of all the earlier ones.

  Any form with two or more quickaddnew fields was affected. The visible symptom was the wrong
  add-form appearing in the dialog; submitting it would have written an object of the wrong class
  and set its ID on the field.

  Fixed by declaring the extension `type: prototype` in `_config/quickaddnew.yml`, which restores
  one instance per field. Reported and reproduced on SilverStripe 5.4.

### Added

- `tests/QuickAddNewIsolationTest.php` - regression coverage for per-field isolation across
  `DropdownField` and `ListboxField`.

### Changed

- The default branch is now `main` (was `master`), with a `dev-main` -> `2.x-dev` branch alias so
  existing `^2` constraints keep resolving.
