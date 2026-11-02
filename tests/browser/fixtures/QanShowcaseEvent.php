<?php

namespace Restruct\QanBrowser;

use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\ListboxField;
use SilverStripe\ORM\DataObject;

/**
 * README-SCREENSHOT FIXTURE ONLY (see QanShowcaseAdmin): an event whose edit form has one
 * quickaddnew field of each supported kind - a has_one DropdownField (Venue), a many_many
 * ListboxField (Speakers) and a many_many CheckboxSetField (Topics). Seeds neutral demo content
 * on dev/build, so the screenshots show a filled-in form.
 *
 * @property string $Title
 * @property int $VenueID
 * @method QanShowcaseVenue Venue()
 * @method \SilverStripe\ORM\ManyManyList Speakers()
 * @method \SilverStripe\ORM\ManyManyList Topics()
 */
class QanShowcaseEvent extends DataObject
{
    private static $table_name = 'QanShowcaseEvent';

    private static $singular_name = 'Event';

    private static $plural_name = 'Events';

    private static $db = [
        'Title' => 'Varchar(255)',
    ];

    private static $has_one = [
        'Venue' => QanShowcaseVenue::class,
    ];

    private static $many_many = [
        'Speakers' => QanShowcaseSpeaker::class,
        'Topics' => QanShowcaseTopic::class,
    ];

    private static $summary_fields = [
        'Title' => 'Title',
        'Venue.Title' => 'Venue',
    ];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName(['VenueID', 'Speakers', 'Topics']);

        # One source callback per class, as the README shows: called once to fill the field and
        # again by doAddNew() so the refreshed field lists the new record.
        $venues = function () {
            return QanShowcaseVenue::get()->sort('Title')->map()->toArray();
        };
        $speakers = function () {
            return QanShowcaseSpeaker::get()->sort('Title')->map()->toArray();
        };
        $topics = function () {
            return QanShowcaseTopic::get()->sort('Title')->map()->toArray();
        };

        $fields->addFieldsToTab('Root.Main', [
            DropdownField::create('VenueID', 'Venue', $venues())
                ->setEmptyString('(choose a venue)')
                ->useAddNew(QanShowcaseVenue::class, $venues),
            ListboxField::create('Speakers', 'Speakers', $speakers())
                ->useAddNew(QanShowcaseSpeaker::class, $speakers),
            CheckboxSetField::create('Topics', 'Topics', $topics())
                ->useAddNew(QanShowcaseTopic::class, $topics),
        ]);

        return $fields;
    }

    /**
     * Seeds the demo content once: the check on the event's title makes this a no-op on a reused
     * host, so records added through the dialog by earlier runs are left as they are.
     */
    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();

        if (QanShowcaseEvent::get()->filter('Title', 'Spring open day')->exists()) {
            return;
        }

        $venues = [];
        foreach ([['Town library', 'Leiden'], ['Harbour hall', 'Rotterdam'], ['Garden pavilion', 'Utrecht']] as [$title, $city]) {
            $venue = QanShowcaseVenue::create(['Title' => $title, 'City' => $city]);
            $venue->write();
            $venues[$title] = $venue;
        }
        $speakers = [];
        foreach (['Anna de Vries', 'Ben Okafor', 'Chen Li', 'Daniel Smit'] as $name) {
            $speaker = QanShowcaseSpeaker::create(['Title' => $name]);
            $speaker->write();
            $speakers[$name] = $speaker;
        }
        $topics = [];
        foreach (['Accessibility', 'Design', 'Open data', 'Performance', 'Security'] as $title) {
            $topic = QanShowcaseTopic::create(['Title' => $title]);
            $topic->write();
            $topics[$title] = $topic;
        }

        $event = QanShowcaseEvent::create(['Title' => 'Spring open day']);
        $event->VenueID = $venues['Town library']->ID;
        $event->write();
        $event->Speakers()->add($speakers['Anna de Vries']);
        $event->Speakers()->add($speakers['Chen Li']);
        $event->Topics()->add($topics['Accessibility']);
        $event->Topics()->add($topics['Open data']);
    }
}
