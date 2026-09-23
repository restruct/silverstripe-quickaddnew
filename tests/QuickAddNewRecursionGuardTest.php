<?php

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;

/**
 * The recursion guard must break a real cycle WITHOUT disabling unrelated nested fields.
 *
 * Up to 2.0.3 the guard was a single process-global boolean, so ANY nested useAddNew() was skipped
 * and any exception thrown while building fields left the flag stuck at true - silently disabling
 * quickaddnew for the rest of the request. 2.1.0 replaces it with a per-class stack popped in a
 * finally block.
 */
class QuickAddNewRecursionGuardTest extends SapphireTest
{
    protected $usesDatabase = false;

    /** Set by the fixtures below so the test can inspect the field built INSIDE getAddNewFields(). */
    public static $innerField = null;

    protected function setUp(): void
    {
        parent::setUp();
        self::$innerField = null;
        $this->clearStack();
    }

    private function stack(): array
    {
        $r = new ReflectionProperty(QuickAddNewExtension::class, 'creating_stack');
        $r->setAccessible(true);

        return $r->getValue();
    }

    private function clearStack(): void
    {
        $r = new ReflectionProperty(QuickAddNewExtension::class, 'creating_stack');
        $r->setAccessible(true);
        $r->setValue(null, []);
    }

    public function testSelfReferencingClassBreaksTheCycle()
    {
        $outer = DropdownField::create('Outer', 'Outer');
        // No explicit $fields, so useAddNew() falls through to getAddNewFields(), which builds a
        // field pointing at the SAME class.
        $outer->useAddNew(QuickAddNewGuardSelfRef::class, fn($obj) => []);

        $this->assertTrue($outer->hasAddNewButton(), 'The outer field must still be enabled.');
        $this->assertNotNull(self::$innerField, 'The fixture should have built an inner field.');
        $this->assertFalse(
            self::$innerField->hasAddNewButton(),
            'The inner field targets the same class as the outer one, so the cycle must be broken.'
        );
    }

    public function testNestedDifferentClassIsStillEnabled()
    {
        $outer = DropdownField::create('Outer', 'Outer');
        $outer->useAddNew(QuickAddNewGuardOuter::class, fn($obj) => []);

        $this->assertTrue($outer->hasAddNewButton());
        $this->assertNotNull(self::$innerField);
        // This is the case the old process-global boolean broke: a DIFFERENT class nested inside
        // was skipped along with the genuine cycle.
        $this->assertTrue(
            self::$innerField->hasAddNewButton(),
            'A nested field on a different class must keep its add-new button.'
        );
    }

    public function testStackIsEmptyAfterASuccessfulCall()
    {
        $field = DropdownField::create('F', 'F');
        $field->useAddNew(QuickAddNewGuardPlain::class, fn($obj) => [], FieldList::create(TextField::create('Title')));

        $this->assertSame([], $this->stack());
    }

    public function testStackIsUnwoundWhenFieldBuildingThrows()
    {
        $field = DropdownField::create('F', 'F');

        try {
            $field->useAddNew(QuickAddNewGuardThrower::class, fn($obj) => []);
            $this->fail('The fixture was supposed to throw.');
        } catch (RuntimeException $e) {
            // expected - the point is what the stack looks like afterwards
        }

        $this->assertSame(
            [],
            $this->stack(),
            'A throw while building fields must not leave the class on the stack; otherwise every '
            . 'later field for that class is silently skipped for the rest of the request.'
        );
    }

    public function testQuickAddNewStillWorksAfterAThrow()
    {
        $broken = DropdownField::create('Broken', 'Broken');
        try {
            $broken->useAddNew(QuickAddNewGuardThrower::class, fn($obj) => []);
        } catch (RuntimeException $e) {
            // expected
        }

        $later = DropdownField::create('Later', 'Later');
        $later->useAddNew(QuickAddNewGuardThrower::class, fn($obj) => [], FieldList::create(TextField::create('Title')));

        $this->assertTrue($later->hasAddNewButton());
    }
}

/**
 * NB each fixture below extends DataObject DIRECTLY and repeats canCreate().
 *
 * These four used to share an `abstract` TestOnly base. That broke every DB-touching test in
 * CONSUMING projects: `TableBuilder::buildTables()` instantiates each manifest class
 * (`new $dataClass([], DataObject::CREATE_SINGLETON)`, TableBuilder.php:28) BEFORE it checks
 * `instanceof TestOnly`, so an abstract DataObject subclass anywhere in the manifest fatals the
 * temp-database build with "Cannot instantiate abstract class". In test mode `ignore_tests` is
 * false, so a vendor module's test fixtures ARE in the manifest.
 *
 * Do not reintroduce an abstract base here. The duplication is deliberate.
 */
class QuickAddNewGuardPlain extends DataObject implements TestOnly
{
    private static $table_name = 'QuickAddNewGuardPlain';

    # A real field, so the class actually gets a table: the add-new forms in these tests
    # are built around a TextField('Title').
    private static $db = ['Title' => 'Varchar'];

    public function canCreate($member = null, $context = [])
    {
        return true;
    }
}

class QuickAddNewGuardSelfRef extends DataObject implements TestOnly
{
    private static $table_name = 'QuickAddNewGuardSelfRef';

    # A real field, so the class actually gets a table: the add-new forms in these tests
    # are built around a TextField('Title').
    private static $db = ['Title' => 'Varchar'];

    public function canCreate($member = null, $context = [])
    {
        return true;
    }

    public function getAddNewFields()
    {
        $inner = DropdownField::create('Inner', 'Inner');
        // Points at its OWN class - this is the genuine cycle the guard exists for.
        $inner->useAddNew(self::class, fn($obj) => []);
        QuickAddNewRecursionGuardTest::$innerField = $inner;

        return FieldList::create($inner);
    }
}

class QuickAddNewGuardOuter extends DataObject implements TestOnly
{
    private static $table_name = 'QuickAddNewGuardOuter';

    # A real field, so the class actually gets a table: the add-new forms in these tests
    # are built around a TextField('Title').
    private static $db = ['Title' => 'Varchar'];

    public function canCreate($member = null, $context = [])
    {
        return true;
    }

    public function getAddNewFields()
    {
        $inner = DropdownField::create('Inner', 'Inner');
        // A DIFFERENT class, so this one must keep working.
        $inner->useAddNew(QuickAddNewGuardPlain::class, fn($obj) => [], FieldList::create(TextField::create('Title')));
        QuickAddNewRecursionGuardTest::$innerField = $inner;

        return FieldList::create($inner);
    }
}

class QuickAddNewGuardThrower extends DataObject implements TestOnly
{
    private static $table_name = 'QuickAddNewGuardThrower';

    # A real field, so the class actually gets a table: the add-new forms in these tests
    # are built around a TextField('Title').
    private static $db = ['Title' => 'Varchar'];

    public function canCreate($member = null, $context = [])
    {
        return true;
    }

    public function getAddNewFields()
    {
        throw new RuntimeException('boom');
    }
}
