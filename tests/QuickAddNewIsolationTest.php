<?php

namespace Restruct\Silverstripe\QuickAddNew\Tests;

use ReflectionProperty;
use Restruct\Silverstripe\QuickAddNew\Extensions\QuickAddNewExtension;
use RuntimeException;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ListboxField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;

/**
 * Two quickaddnew-enabled fields on one request must each keep their OWN registration.
 *
 * Regression test for the Injector-singleton defect: QuickAddNewExtension stores per-field state on
 * itself, and Extensible::getExtensionInstances() resolves extensions via Injector::inst()->get(),
 * which treats an unregistered class as a SINGLETON. Without the `type: prototype` declaration in
 * _config/quickaddnew.yml, both fields share one extension instance and the LAST useAddNew() call
 * wins for all of them - so every dialog opens the wrong class's form, and doAddNew() writes that
 * wrong class.
 *
 * MUST-FAIL CONTROL: comment out the Injector block in _config/quickaddnew.yml and both
 * testEachFieldKeepsItsOwnClass and testExtensionInstancesAreNotShared fail.
 */
class QuickAddNewIsolationTest extends SapphireTest
{
    protected $usesDatabase = false;

    /**
     * Build a field with useAddNew() already applied.
     *
     * Explicit $fields are passed so useAddNew() never falls through to getCMSFields() scaffolding,
     * which would need a built database and make this test about something else.
     */
    private function fieldFor(string $name, string $class, $fieldClass = DropdownField::class)
    {
        $field = $fieldClass::create($name, $name);
        $field->useAddNew(
            $class,
            function ($obj) use ($name) {
                return [$name => $name];
            },
            FieldList::create(TextField::create('Title'))
        );

        return $field;
    }

    private function readState($field, string $prop)
    {
        $ext = $field->getExtensionInstance(QuickAddNewExtension::class);
        $reflection = new ReflectionProperty(QuickAddNewExtension::class, $prop);
        $reflection->setAccessible(true);

        return $reflection->getValue($ext);
    }

    public function testExtensionInstancesAreNotShared()
    {
        $a = $this->fieldFor('A', QuickAddNewTestAlpha::class);
        $b = $this->fieldFor('B', QuickAddNewTestBeta::class);

        $this->assertNotSame(
            $a->getExtensionInstance(QuickAddNewExtension::class),
            $b->getExtensionInstance(QuickAddNewExtension::class),
            'Each field must get its own QuickAddNewExtension instance; a shared instance means '
            . 'per-field state leaks between fields.'
        );
    }

    public function testEachFieldKeepsItsOwnClass()
    {
        // Registration order matters: with a shared instance the SECOND call overwrites the first.
        $a = $this->fieldFor('A', QuickAddNewTestAlpha::class);
        $b = $this->fieldFor('B', QuickAddNewTestBeta::class);

        $this->assertSame(QuickAddNewTestAlpha::class, $this->readState($a, 'addNewClass'));
        $this->assertSame(QuickAddNewTestBeta::class, $this->readState($b, 'addNewClass'));
    }

    public function testEachFieldKeepsItsOwnSourceCallback()
    {
        $a = $this->fieldFor('A', QuickAddNewTestAlpha::class);
        $b = $this->fieldFor('B', QuickAddNewTestBeta::class);

        $callbackA = $this->readState($a, 'sourceCallback');
        $callbackB = $this->readState($b, 'sourceCallback');

        // The callback is what doAddNew() uses to repopulate the field's source, so a leaked
        // callback repopulates the wrong field.
        $this->assertSame(['A' => 'A'], $callbackA(null));
        $this->assertSame(['B' => 'B'], $callbackB(null));
    }

    public function testIsolationHoldsAcrossFieldTypes()
    {
        // DropdownField and ListboxField are extended by the same class, so a singleton would leak
        // across the two types as well.
        $a = $this->fieldFor('A', QuickAddNewTestAlpha::class, DropdownField::class);
        $b = $this->fieldFor('B', QuickAddNewTestBeta::class, ListboxField::class);

        $this->assertSame(QuickAddNewTestAlpha::class, $this->readState($a, 'addNewClass'));
        $this->assertSame(QuickAddNewTestBeta::class, $this->readState($b, 'addNewClass'));
    }

    public function testCheckboxSetFieldIsExtendedAndIsolated()
    {
        // CheckboxSetField registration was pulled from upstream in 2.1.0; the README had described
        // it as supported long before this module's _config registered it.
        $a = $this->fieldFor('A', QuickAddNewTestAlpha::class, CheckboxSetField::class);
        $b = $this->fieldFor('B', QuickAddNewTestBeta::class, CheckboxSetField::class);

        $this->assertTrue($a->hasAddNewButton(), 'CheckboxSetField must get the quickaddnew extension.');
        $this->assertSame(QuickAddNewTestAlpha::class, $this->readState($a, 'addNewClass'));
        $this->assertSame(QuickAddNewTestBeta::class, $this->readState($b, 'addNewClass'));
    }

    public function testFieldsWithoutUseAddNewDoNotReportAButton()
    {
        // Guards against the opposite leak: a field that never called useAddNew() must not inherit
        // another field's enabled state.
        $plain = DropdownField::create('Plain', 'Plain');
        $this->fieldFor('A', QuickAddNewTestAlpha::class);

        $this->assertFalse($plain->hasAddNewButton());
    }
}

class QuickAddNewTestAlpha extends DataObject implements TestOnly
{
    private static $table_name = 'QuickAddNewTestAlpha';

    public function canCreate($member = null, $context = [])
    {
        return true;
    }
}

class QuickAddNewTestBeta extends DataObject implements TestOnly
{
    private static $table_name = 'QuickAddNewTestBeta';

    public function canCreate($member = null, $context = [])
    {
        return true;
    }
}
