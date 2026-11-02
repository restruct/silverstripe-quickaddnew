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
class QanShowcaseTopic extends DataObject
{
    private static $table_name = 'QanShowcaseTopic';

    private static $singular_name = 'Topic';

    private static $plural_name = 'Topics';

    private static $db = [
        'Title' => 'Varchar(255)',
    ];

    public function getAddNewFields()
    {
        return FieldList::create(TextField::create('Title', 'Title'));
    }
}
