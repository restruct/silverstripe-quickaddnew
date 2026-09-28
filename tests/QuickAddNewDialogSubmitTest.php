<?php

use SilverStripe\Control\Controller;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;

/**
 * The dialog submit as quickaddnew.js now sends it: a plain ajax POST (FormData, not jquery.form's
 * ajaxSubmit) to the field's AddNewForm URL, carrying the clicked button's action_doAddNew key.
 *
 * This is the server half of the ajaxSubmit fix only. The JS itself has no test harness; that the
 * browser builds this request is covered by the manual browser check, not here.
 */
class QuickAddNewDialogSubmitTest extends FunctionalTest
{
    protected $usesDatabase = true;

    protected static $extra_controllers = [
        QuickAddNewDialogSubmitController::class,
    ];

    protected static $extra_dataobjects = [
        QuickAddNewDialogSubmitThing::class,
    ];

    private function addNewUrl(): string
    {
        return 'quickaddnew-dialog-submit-test/Form/field/ThingID/AddNewForm';
    }

    public function testAjaxPostWithActionKeyWritesAndReturnsTheFieldHolder()
    {
        $response = $this->post(
            $this->addNewUrl(),
            ['Title' => 'Made in the dialog', 'action_doAddNew' => 'Add'],
            ['X-Requested-With' => 'XMLHttpRequest']
        );

        $this->assertSame(200, $response->getStatusCode());
        $thing = QuickAddNewDialogSubmitThing::get()->filter('Title', 'Made in the dialog')->first();
        $this->assertNotNull($thing, 'Submitting the dialog must write the record.');

        // quickaddnew.js's success handler checks $(res).is(".field") to decide it got the refreshed
        // field back, then takes the new <option> from it.
        $body = (string) $response->getBody();
        $this->assertMatchesRegularExpression('/^\s*<div[^>]*class="[^"]*\bfield\b/', $body);
        // The option template spreads attributes over several lines, hence \s+.
        $this->assertMatchesRegularExpression('/value="' . $thing->ID . '"\s+selected="selected"/', $body);
    }

    public function testAjaxValidationFailureReturnsTheFormAsHtml()
    {
        // The success handler shows a non-.field response in the dialog, so a validation failure
        // must come back as the re-rendered form (200 text/html), which is what dataType "html"
        // in quickaddnew.js asks for.
        $response = $this->post(
            $this->addNewUrl(),
            ['Title' => '', 'action_doAddNew' => 'Add'],
            ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html, */*; q=0.01']
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('<form', (string) $response->getBody());
        $this->assertSame(0, QuickAddNewDialogSubmitThing::get()->count());
    }
}

class QuickAddNewDialogSubmitController extends Controller implements TestOnly
{
    private static $url_segment = 'quickaddnew-dialog-submit-test';

    private static $allowed_actions = ['Form'];

    public function Form()
    {
        $source = function () {
            return QuickAddNewDialogSubmitThing::get()->map()->toArray();
        };
        $field = DropdownField::create('ThingID', 'Thing', $source())
            ->useAddNew(
                QuickAddNewDialogSubmitThing::class,
                $source,
                FieldList::create(TextField::create('Title')),
                RequiredFields::create('Title')
            );

        return Form::create($this, 'Form', FieldList::create($field), FieldList::create());
    }
}

class QuickAddNewDialogSubmitThing extends DataObject implements TestOnly
{
    private static $table_name = 'QuickAddNewDialogSubmitThing';

    private static $db = ['Title' => 'Varchar'];

    public function canCreate($member = null, $context = [])
    {
        return true;
    }
}
