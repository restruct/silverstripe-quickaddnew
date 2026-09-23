# Changelog

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
