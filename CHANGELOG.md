# Changelog

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
