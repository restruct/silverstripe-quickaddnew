# Upgrading

## 2.x to 3.0

3.0 targets Silverstripe 6 and PHP 8.3+. The Silverstripe 5 line continues on the `v2` branch
(`^2` constraints keep resolving there) and receives security and bug fixes until Silverstripe 5
reaches end of life in April 2027.

There is one breaking change.

### The extension class is now namespaced

Up to 2.x, `QuickAddNewExtension` lived in the global namespace and was found only through
Silverstripe's class manifest; the package declared no `autoload` section at all. It is now
`Restruct\Silverstripe\QuickAddNew\Extensions\QuickAddNewExtension`, autoloaded via PSR-4.

You are affected only if your project refers to the class **by name**. Calling `useAddNew()` on a
field is unchanged and needs no edit.

Search your project for the bare class name:

```bash
grep -rn "QuickAddNewExtension" app/ --include='*.php' --include='*.yml'
```

Typical places it appears, and what to change:

    Before                                          After
    'QuickAddNewExtension'                          Restruct\Silverstripe\QuickAddNew\Extensions\QuickAddNewExtension
    $field->getExtensionInstance('QuickAddNew...')  $field->getExtensionInstance(QuickAddNewExtension::class)
    SomeField:                                      SomeField:
      extensions:                                     extensions:
        - 'QuickAddNewExtension'                        - 'Restruct\Silverstripe\QuickAddNew\Extensions\QuickAddNewExtension'

If you carried a project-level Injector declaration for the prototype fix (see below), its key must
move to the new class name too, or it silently stops applying.

### Things that did NOT change

- `useAddNew($class, $sourceCallback, $fields, $required, $isFrontend)` keeps its signature, except
  that `$required` is now typed `?RequiredFieldsValidator` rather than `?RequiredFields`, following
  Silverstripe 6's move of that class to `SilverStripe\Forms\Validation\RequiredFieldsValidator`.
  Projects that pass their own validator must construct the new class.
- The `updateQuickAddNewForm` extension hook.
- The JavaScript and CSS, and the `data-quickaddnew-action` attribute contract.
- `DropdownField`, `ListboxField` and `CheckboxSetField` are all still registered.

### If you are coming from 2.0.2 or earlier

Read the 2.0.3 and 2.1.1 entries in the changelog before upgrading. 2.0.3 fixed a defect where all
quickaddnew fields on a form shared one extension instance, so every "Add New" dialog opened the
last-registered class's form and could write the wrong class. If your project carries a local
workaround for that (an Injector `type: prototype` declaration in your own `_config`), remove it
after upgrading: the module ships the declaration itself.
