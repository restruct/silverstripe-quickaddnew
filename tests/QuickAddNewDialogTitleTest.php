<?php

namespace Restruct\Silverstripe\QuickAddNew\Tests;

use SilverStripe\Control\Controller;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\TextField;
use SilverStripe\i18n\i18n;
use SilverStripe\ORM\DataObject;
use Symfony\Component\Yaml\Yaml;

/**
 * The Add New dialog's title: the "Add new {singular name}" default, the setAddNewDialogTitle()
 * override, and per-field isolation of both (issue #1).
 *
 * MUST-FAIL CONTROLS (both run when this was written):
 * - comment out the `$attributes['data-dialog-title'] = ...` default in updateAttributes() and the
 *   default-title tests fail;
 * - comment out the Injector block in _config/quickaddnew.yml (with `#`, then flush) and
 *   testTwoFieldsKeepTheirOwnDefaultTitles and testFieldWithoutUseAddNewGetsNoTitle fail: the
 *   default is derived from the extension's $addNewClass, which a shared extension instance
 *   overwrites. testTwoFieldsOnTheSameClassKeepTheirOwnOverrides keeps passing without the
 *   declaration, by design - the override is stored on the field, not on the extension.
 */
class QuickAddNewDialogTitleTest extends SapphireTest
{
    protected $usesDatabase = false;

    /**
     * A field with useAddNew() applied, inside a form with a controller behind it.
     *
     * updateAttributes() builds the dialog URL from $field->Link(), which needs a form and a
     * controller. Explicit add-new fields are passed so useAddNew() never scaffolds getCMSFields(),
     * which would need a database.
     */
    private function fieldInForm(string $name, string $class, ?string $title = null)
    {
        $field = DropdownField::create($name, $name);
        if ($title !== null) {
            $field->setAddNewDialogTitle($title);
        }
        $field->useAddNew(
            $class,
            function ($obj) {
                return [];
            },
            FieldList::create(TextField::create('Title'))
        );

        Form::create(
            QuickAddNewDialogTitleController::create(),
            'TestForm',
            FieldList::create($field),
            FieldList::create()
        );

        return $field;
    }

    private function titleOf($field)
    {
        return $field->getAttributes()['data-dialog-title'] ?? null;
    }

    public function testDefaultTitleUsesTheSingularName()
    {
        $field = $this->fieldInForm('FieldA', QuickAddNewDialogTitleAlpha::class);

        $this->assertSame('Add new Alpha Thing', $this->titleOf($field));
        $this->assertSame('Add new Alpha Thing', $field->getAddNewDialogTitle());
        // What the JS reads is the rendered attribute, so check the HTML too.
        $this->assertStringContainsString('data-dialog-title="Add new Alpha Thing"', (string) $field->Field());
    }

    public function testOverrideWins()
    {
        $field = $this->fieldInForm('FieldA', QuickAddNewDialogTitleAlpha::class);
        $result = $field->setAddNewDialogTitle('Add a tag type');

        $this->assertSame($field, $result, 'setAddNewDialogTitle() must be chainable.');
        $this->assertSame('Add a tag type', $this->titleOf($field));
        $this->assertSame('Add a tag type', $field->getAddNewDialogTitle());
    }

    public function testOverrideSetBeforeUseAddNewWins()
    {
        $field = $this->fieldInForm('FieldA', QuickAddNewDialogTitleAlpha::class, 'Set first');

        $this->assertSame('Set first', $this->titleOf($field));
    }

    public function testClearingTheOverrideRestoresTheDefault()
    {
        $field = $this->fieldInForm('FieldA', QuickAddNewDialogTitleAlpha::class, 'Custom');
        $field->setAddNewDialogTitle(null);
        $this->assertSame('Add new Alpha Thing', $this->titleOf($field));

        $field->setAddNewDialogTitle('');
        $this->assertSame('Add new Alpha Thing', $this->titleOf($field));
    }

    public function testTwoFieldsKeepTheirOwnDefaultTitles()
    {
        // Registration order matters: with a shared extension instance the SECOND useAddNew()
        // overwrites the first field's class, and with it the first field's default title.
        $a = $this->fieldInForm('FieldA', QuickAddNewDialogTitleAlpha::class);
        $b = $this->fieldInForm('FieldB', QuickAddNewDialogTitleBeta::class);

        $this->assertSame('Add new Alpha Thing', $this->titleOf($a));
        $this->assertSame('Add new Beta Thing', $this->titleOf($b));
    }

    public function testTwoFieldsOnTheSameClassKeepTheirOwnOverrides()
    {
        // The case the override exists for: two relations to the SAME class, so the default
        // titles are identical and only the override tells the dialogs apart.
        $a = $this->fieldInForm('FieldA', QuickAddNewDialogTitleAlpha::class, 'Add a document type');
        $b = $this->fieldInForm('FieldB', QuickAddNewDialogTitleAlpha::class, 'Add a work area');

        $this->assertSame('Add a document type', $this->titleOf($a));
        $this->assertSame('Add a work area', $this->titleOf($b));
    }

    public function testFieldWithoutUseAddNewGetsNoTitle()
    {
        $plain = DropdownField::create('Plain', 'Plain');
        $this->fieldInForm('FieldA', QuickAddNewDialogTitleAlpha::class);

        $this->assertArrayNotHasKey('data-dialog-title', $plain->getAttributes());
        $this->assertNull($plain->getAddNewDialogTitle());
    }

    public function testFieldWithoutAFormStillGetsItsTitle()
    {
        // updateAttributes() used to call getController() on a null form here.
        $field = DropdownField::create('Formless', 'Formless');
        $field->useAddNew(
            QuickAddNewDialogTitleAlpha::class,
            function ($obj) {
                return [];
            },
            FieldList::create(TextField::create('Title'))
        );

        $this->assertSame('Add new Alpha Thing', $this->titleOf($field));
        $this->assertArrayNotHasKey('data-quickaddnew-action', $field->getAttributes());
    }

    public function testLangKeyIsPresentInEnglishAndDutch()
    {
        $langDir = dirname(__DIR__) . '/lang';
        foreach (['en', 'nl'] as $locale) {
            $messages = Yaml::parseFile("$langDir/$locale.yml");
            $this->assertArrayHasKey(
                'AddNewTitle',
                $messages[$locale]['QUICKADDNEW'] ?? [],
                "lang/$locale.yml must define QUICKADDNEW.AddNewTitle"
            );
            $this->assertStringContainsString('{type}', $messages[$locale]['QUICKADDNEW']['AddNewTitle']);
        }
    }

    public function testDutchTitleIsTranslated()
    {
        $field = $this->fieldInForm('FieldA', QuickAddNewDialogTitleAlpha::class);

        $title = i18n::with_locale('nl_NL', function () use ($field) {
            return $this->titleOf($field);
        });

        $this->assertSame('Alpha Thing toevoegen', $title);
    }
}

class QuickAddNewDialogTitleController extends Controller implements TestOnly
{
    // RequestHandler::Link() raises a warning without a url_segment.
    private static $url_segment = 'quickaddnew-dialog-title-test';
}

class QuickAddNewDialogTitleAlpha extends DataObject implements TestOnly
{
    private static $table_name = 'QuickAddNewDialogTitleAlpha';

    private static $singular_name = 'Alpha Thing';

    public function canCreate($member = null, $context = [])
    {
        return true;
    }
}

class QuickAddNewDialogTitleBeta extends DataObject implements TestOnly
{
    private static $table_name = 'QuickAddNewDialogTitleBeta';

    private static $singular_name = 'Beta Thing';

    public function canCreate($member = null, $context = [])
    {
        return true;
    }
}
