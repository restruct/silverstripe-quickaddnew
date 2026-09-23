<?php

namespace Restruct\Silverstripe\QuickAddNew\Tests;

use SilverStripe\Control\Controller;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;

/**
 * Covers what the field RENDERS and what submitting the dialog actually WRITES.
 *
 * The existing suites assert PHP object state through reflection, which leaves four of the
 * extension's seven public methods untested - including doAddNew(), the one that writes to the
 * database. These are the tests that fail on the 2.0.2 defect at the level a user would notice it:
 * two fields emitting the same dialog URL, and a submit writing the wrong class.
 */
class QuickAddNewOutputTest extends SapphireTest
{
    protected $usesDatabase = true;

    private function fieldInForm(string $name, string $class, ?FieldList $addNewFields = null): DropdownField
    {
        $field = DropdownField::create($name, $name);
        $field->useAddNew(
            $class,
            fn($obj) => [],
            $addNewFields ?: FieldList::create(TextField::create('Title'))
        );

        // updateAttributes() builds its URL from $field->Link(), which needs a form with a
        // controller behind it - a bare field returns nothing useful. The controller declares a
        // url_segment because RequestHandler::Link() raises a WARNING without one, and CI pins
        // failOnWarning.
        Form::create(
            QuickAddNewOutputController::create(),
            'TestForm',
            FieldList::create($field),
            FieldList::create()
        );

        return $field;
    }

    /**
     * NB this does NOT guard the shared-state defect, and must not be read as doing so: the URL is
     * built from $this->owner->Link(), and the owner is pushed per call, so it is correct even when
     * every field shares one extension instance. It covers updateAttributes() emitting an endpoint
     * at all, which nothing else tested.
     */
    public function testEachFieldRendersItsOwnDialogUrl()
    {
        $a = $this->fieldInForm('FieldA', QuickAddNewOutputAlpha::class);
        $b = $this->fieldInForm('FieldB', QuickAddNewOutputBeta::class);

        $attrA = $a->getAttributes();
        $attrB = $b->getAttributes();

        $this->assertArrayHasKey('data-quickaddnew-action', $attrA, 'updateAttributes must emit the dialog URL.');
        $this->assertArrayHasKey('data-quickaddnew-action', $attrB);
        $this->assertNotSame(
            $attrA['data-quickaddnew-action'],
            $attrB['data-quickaddnew-action'],
            'Two quickaddnew fields must point their dialogs at their OWN endpoints.'
        );
    }

    public function testDialogFormIsBuiltForTheFieldsOwnClass()
    {
        // The assertion has to be on the form's FIELDS, not its name or action: those come from
        // the owner, which is correct even with a shared extension instance. The field list is
        // per-registration state, so it is what actually moves when the state leaks.
        $groupField = $this->fieldInForm(
            'GroupField',
            Group::class,
            FieldList::create(TextField::create('Title'))
        );
        $memberField = $this->fieldInForm(
            'MemberField',
            Member::class,
            FieldList::create(TextField::create('FirstName'))
        );

        $groupHtml = $groupField->AddNewFormHTML();

        // Registration order matters: Member is registered SECOND, so a shared instance makes the
        // GROUP dialog render the Member form.
        $this->assertStringContainsString('Title', $groupHtml);
        $this->assertStringNotContainsString('FirstName', $groupHtml);
        $this->assertStringContainsString('FirstName', $memberField->AddNewFormHTML());
    }

    public function testSubmittingTheDialogWritesTheFieldsOwnClass()
    {
        // Real framework classes rather than this suite's fixtures: a module's own TestOnly
        // DataObjects get no tables when the suite is run module-locally (they are loaded by
        // PHPUnit, not registered in the class manifest), and this test has to actually write.
        $this->logInWithPermission('ADMIN');

        // Member is registered FIRST and Group SECOND, deliberately: with a shared extension
        // instance the last registration wins, so submitting the MEMBER field would create a
        // Group. Registering Member last would make this test pass on the broken code.
        $memberField = $this->fieldInForm(
            'MemberField',
            Member::class,
            FieldList::create(TextField::create('FirstName'), TextField::create('Surname'))
        );
        $groupField = $this->fieldInForm('GroupField', Group::class);

        $groupsBefore = Group::get()->count();
        $membersBefore = Member::get()->count();

        $form = $memberField->AddNewForm();
        $data = ['FirstName' => 'Written', 'Surname' => 'ByMemberField'];
        $form->loadDataFrom($data);
        $memberField->doAddNew($data, $form);

        // The damage path: on 2.0.2 the member field's submit would have created whichever class
        // was registered LAST, because doAddNew() read the shared $addNewClass.
        $this->assertSame($groupsBefore, Group::get()->count(), 'No Group should have been created.');
        $this->assertSame($membersBefore + 1, Member::get()->count(), 'The member field must create a Member.');

        $created = Member::get()->sort('ID', 'DESC')->first();
        $this->assertSame('ByMemberField', $created->Surname);
        $this->assertSame((string) $created->ID, (string) $memberField->getValue(), 'The new ID must be set on the field.');

        // Unused here beyond registration order, but naming it keeps the intent obvious.
        $this->assertTrue($groupField->hasAddNewButton());
    }
}

class QuickAddNewOutputController extends Controller implements TestOnly
{
    private static $url_segment = 'quickaddnew-output-test';
}

class QuickAddNewOutputAlpha extends DataObject implements TestOnly
{
    private static $table_name = 'QuickAddNewOutputAlpha';

    private static $db = ['Title' => 'Varchar'];

    public function canCreate($member = null, $context = [])
    {
        return true;
    }
}

class QuickAddNewOutputBeta extends DataObject implements TestOnly
{
    private static $table_name = 'QuickAddNewOutputBeta';

    private static $db = ['Title' => 'Varchar'];

    public function canCreate($member = null, $context = [])
    {
        return true;
    }
}
