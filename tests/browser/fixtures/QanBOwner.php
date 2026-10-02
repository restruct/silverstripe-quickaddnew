<?php

namespace Restruct\QanBrowser;

use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\ListboxField;
use SilverStripe\ORM\DataObject;

/**
 * BROWSER-TEST FIXTURE ONLY - a record whose CMS form carries the three quickaddnew field shapes
 * the specs drive (see QanBTag for why this never loads in a real install):
 *
 * - TagID      DropdownField, default dialog title ("Add new Browser Tag")
 * - OtherTagID DropdownField, overridden dialog title ("Add a work area")
 * - Tags       ListboxField (many_many), which must keep earlier selections after an add
 *
 * @property string $Title
 * @method QanBTag Tag()
 * @method QanBTag OtherTag()
 * @method \SilverStripe\ORM\ManyManyList Tags()
 */
class QanBOwner extends DataObject
{
    private static $table_name = 'QanBOwner';

    private static $singular_name = 'Browser Owner';

    private static $db = [
        'Title' => 'Varchar(255)',
    ];

    private static $has_one = [
        'Tag' => QanBTag::class,
        'OtherTag' => QanBTag::class,
    ];

    private static $many_many = [
        'Tags' => QanBTag::class,
    ];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName(['TagID', 'OtherTagID', 'Tags']);

        # The source callback: called again by doAddNew() so the refreshed field lists the new tag.
        $source = function () {
            return QanBTag::get()->map()->toArray();
        };

        $fields->addFieldsToTab('Root.Main', [
            DropdownField::create('TagID', 'Tag', $source())
                ->setEmptyString('(none)')
                ->useAddNew(QanBTag::class, $source),
            DropdownField::create('OtherTagID', 'Work area', $source())
                ->setEmptyString('(none)')
                ->setAddNewDialogTitle('Add a work area')
                ->useAddNew(QanBTag::class, $source),
            ListboxField::create('Tags', 'Tags', $source())
                ->useAddNew(QanBTag::class, $source),
        ]);

        return $fields;
    }
}
