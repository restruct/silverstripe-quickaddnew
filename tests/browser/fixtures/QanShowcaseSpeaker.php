<?php

namespace Restruct\QanBrowser;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;

/**
 * README-SCREENSHOT FIXTURE ONLY (see QanShowcaseAdmin).
 *
 * @property string $Title
 */
class QanShowcaseSpeaker extends DataObject
{
    private static $table_name = 'QanShowcaseSpeaker';

    private static $singular_name = 'Speaker';

    private static $plural_name = 'Speakers';

    private static $db = [
        'Title' => 'Varchar(255)',
    ];

    public function getAddNewFields()
    {
        return FieldList::create(TextField::create('Title', 'Name'));
    }
}
