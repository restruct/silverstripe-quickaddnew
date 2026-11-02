<?php

namespace Restruct\QanBrowser;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;

/**
 * README-SCREENSHOT FIXTURE ONLY (see QanShowcaseAdmin). Shows the two optional hooks on the
 * created class: getAddNewFields() (the dialog's fields, instead of getCMSFields()) and
 * getAddNewValidator() (its required fields).
 *
 * @property string $Title
 * @property string $City
 */
class QanShowcaseVenue extends DataObject
{
    private static $table_name = 'QanShowcaseVenue';

    private static $singular_name = 'Venue';

    private static $plural_name = 'Venues';

    private static $db = [
        'Title' => 'Varchar(255)',
        'City' => 'Varchar(255)',
    ];

    public function getAddNewFields()
    {
        return FieldList::create(
            TextField::create('Title', 'Name'),
            TextField::create('City', 'City')
        );
    }

    /**
     * Written to load on Silverstripe 5 and 6 alike (RequiredFields moved and was renamed in 6).
     */
    public function getAddNewValidator()
    {
        $class = class_exists('SilverStripe\\Forms\\Validation\\RequiredFieldsValidator')
            ? 'SilverStripe\\Forms\\Validation\\RequiredFieldsValidator'
            : 'SilverStripe\\Forms\\RequiredFields';

        return $class::create(['Title']);
    }
}
