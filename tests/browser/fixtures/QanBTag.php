<?php

namespace Restruct\QanBrowser;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;

/**
 * BROWSER-TEST FIXTURE ONLY - the object the Add New dialog creates.
 *
 * Never loaded by a real install: it lives under tests/browser/, which carries a _manifest_exclude
 * marker, and the browser-test runner copies it into a scratch host's app/ before dev/build.
 * Written to load on both Silverstripe 5 and 6 (no class imports that moved between the two).
 *
 * @property string $Title
 */
class QanBTag extends DataObject
{
    # Short table name: the many_many join table is {ownerTable}_{Relation}, and the namespaced
    # default would be needlessly long (MySQL caps table names at 64 characters).
    private static $table_name = 'QanBTag';

    private static $singular_name = 'Browser Tag';

    private static $plural_name = 'Browser Tags';

    private static $db = [
        'Title' => 'Varchar(255)',
    ];

    /**
     * The dialog's fields (QuickAddNewExtension::useAddNew() prefers this over getCMSFields()).
     */
    public function getAddNewFields()
    {
        return FieldList::create(TextField::create('Title', 'Title'));
    }

    /**
     * Title is required in the dialog. The class was renamed in SS6
     * (RequiredFields -> Validation\RequiredFieldsValidator), so pick whichever this framework has.
     */
    public function getAddNewValidator()
    {
        $class = class_exists('SilverStripe\\Forms\\Validation\\RequiredFieldsValidator')
            ? 'SilverStripe\\Forms\\Validation\\RequiredFieldsValidator'
            : 'SilverStripe\\Forms\\RequiredFields';

        return $class::create(['Title']);
    }

    /**
     * Server-side rejection, so the spec can check the dialog shows a validation message returned by
     * doAddNew() (which catches the write exception and re-renders the form with it).
     * Any title starting with "REJECT" fails. ValidationException moved namespace in SS6.
     */
    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();

        if (strpos((string) $this->Title, 'REJECT') === 0) {
            $class = class_exists('SilverStripe\\Core\\Validation\\ValidationException')
                ? 'SilverStripe\\Core\\Validation\\ValidationException'
                : 'SilverStripe\\ORM\\ValidationException';

            throw new $class('Browser fixture: titles starting with REJECT are not allowed');
        }
    }
}
