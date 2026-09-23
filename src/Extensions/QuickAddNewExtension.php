<?php

namespace Restruct\Silverstripe\QuickAddNew\Extensions;

use Exception;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Validation\RequiredFieldsValidator;
use SilverStripe\View\Requirements;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\Form;
use SilverStripe\Security\Security;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Injector\Injector;

/**
 * QuickAddNewExtension
 *
 * @package silverstripe-quickaddnew
 * @author shea@silverstripe.com.au
 **/
class QuickAddNewExtension extends Extension
{
    /**
     * @var boolean
     */
    protected $addNewEnabled = false;

    /**
     * @var FieldList
     **/
    protected $addNewFields;

    /**
     * @var string
     **/
    protected $addNewClass;


    /**
     * @var callable
     **/
    protected $sourceCallback;


    /**
     * @var RequiredFieldsValidator
     **/
    protected $addNewRequiredFields;


    /**
     * @var bool
     **/
    protected $isFrontend;

    /**
     * @var array
     */
    private static $allowed_actions = array(
        'AddNewForm',
        'AddNewFormHTML',
        'doAddNew'
    );

    /**
     * Classes whose add-new fields are currently being built, innermost last.
     *
     * This replaces the former process-global boolean:
     *
     *        protected static $is_creating = false;
     *
     * which had two problems. It was a single flag for the whole request, so a nested useAddNew()
     * on a DIFFERENT class was silently skipped rather than only the genuine cycle being broken;
     * and it was cleared by a plain assignment, so any exception thrown while building the fields
     * left it stuck at true and silently disabled quickaddnew on every later field in the request.
     *
     * A stack keyed by class breaks only the real cycle (a Tag whose add-new form contains a Tag
     * field), and the pop below is in a finally block.
     *
     * @var array<string>
     */
    protected static $creating_stack = [];

    /**
     * Tell this form field to apply the add new UI and functionality
     *
     * @param class-string $class - the class name of the object being managed on the relationship
     * @param callable $sourceCallback - the function called to repopulate the field's source array
     * @param FieldList $fields - Fields to create the object via dialog form - defaults to the object's getAddNewFields() method
     * @param RequiredFieldsValidator $required - to create the validator for the dialog form
     * @param bool $isFrontend - If this is set to true, the css classes for the CMS ui will not be set of the form elements
     * this also opens the opportunity to manipulate the form for Frontend uses via an extension
     * @return FormField $this->owner
     **/
    public function useAddNew(
        $class,
        $sourceCallback,
        ?FieldList $fields = null,
        ?RequiredFieldsValidator $required = null,
        $isFrontend = false
    ) {
        if (!is_callable($sourceCallback)) {
            throw new Exception(
                'the useAddNew method must be passed a callable $sourceCallback parameter, ' . gettype($sourceCallback) . ' passed.'
            );
        }

        $sng = singleton($class);

        // if the user can't create this object type, don't modify the form
        if (!$sng->canCreate()) {
            return $this->owner;
        }

        // Avoid a nested loop if the class displays itself, eg a Tag whose add-new form contains a
        // Tag field. Only THIS class is blocked, so an add-new form may still offer add-new on a
        // different class.
        if (in_array($class, self::$creating_stack, true)) {
            return $this->owner;
        }
        self::$creating_stack[] = $class;

        try {
            Requirements::javascript('restruct/silverstripe-quickaddnew:/client/javascript/quickaddnew.js');
            Requirements::css('restruct/silverstripe-quickaddnew:/client/css/quickaddnew.css');
            Requirements::add_i18n_javascript('restruct/silverstripe-quickaddnew:/client/javascript/lang');

            if (!$fields) {
                if ($sng->hasMethod('getAddNewFields')) {
                    $fields =  $sng->getAddNewFields();
                } else {
                    $fields = $sng->getCMSFields();
                }
            }

            if (!$required) {
                if ($sng->hasMethod('getAddNewValidator')) {
                    $required = $sng->getAddNewValidator();
                }
            }
        } finally {
            // finally, so a throw while building the fields cannot leave this class stuck on the
            // stack and silently disable quickaddnew for the rest of the request.
            array_pop(self::$creating_stack);
        }

        $this->owner->addExtraClass('quickaddnew-field');
        $this->addNewEnabled = true;

        $this->sourceCallback = $sourceCallback;
        $this->isFrontend = $isFrontend;
        $this->addNewClass = $class;
        $this->addNewFields = $fields;
        $this->addNewRequiredFields = $required;

        return $this->owner;
    }

    /**
     * @return boolean
     */
    public function hasAddNewButton()
    {
        return $this->addNewEnabled;
    }

    /**
     *
     */
    public function updateAttributes(&$attributes)
    {
        if (!$this->addNewFields) {
            // Ignore if not using QuickAddNew
            return;
        }
        $form = $this->owner->getForm();
        if ($this->owner === $form->getController()) {
            // Ignore action to avoid cyclic calls with Link() function
            return;
        }
        $action = $this->owner->Link('AddNewFormHTML');
        // Remove [] for ListboxSetField/CheckboxSetField
        $action = preg_replace("/[\[\]']+/", "", $action);
        $attributes['data-quickaddnew-action'] = $action;
    }

    /**
     * The AddNewForm for the dialog window
     *
     * @return Form
     **/
    public function AddNewForm()
    {
        $action = FormAction::create('doAddNew', _t('QUICKADDNEW.Add', 'Add'))->setUseButtonTag('true');

        if (!$this->isFrontend) {
            $action->addExtraClass('ss-ui-action-constructive btn btn-success font-icon font-icon-save');
        }

        $actions = FieldList::create($action);
        $form = Form::create($this->owner, 'AddNewForm', $this->addNewFields, $actions, $this->addNewRequiredFields);

        $this->owner->extend('updateQuickAddNewForm', $form);

        return $form;
    }


    /**
     * Returns the HTML of the AddNewForm for the dialog
     *
     * @return string
     **/
    public function AddNewFormHTML()
    {
        return $this->AddNewForm()->forTemplate();
    }


    /**
     * Handles adding the new object
     * Returns the updated FieldHolder of this form to replace the existing one
     *
     * @return string
     **/
    public function doAddNew($data, $form)
    {
        $obj = Injector::inst()->create($this->addNewClass);
        if (!$obj->canCreate()) {
            return Security::permissionFailure(Controller::curr(), "You don't have permission to create this object");
        }
        $form->saveInto($obj);

        try {
            $obj->write();
        } catch (Exception $e) {
            $form->setMessage($e->getMessage(), 'error');
            return $form->forTemplate();
        }

        $callback = $this->sourceCallback;
        $items = $callback($obj);
        $this->owner->setSource($items);

        // if this field is a multiselect field, we add the new Object ID to the existing
        // options that are selected on the field then set that as the value
        // otherwise we just set the new Object ID as the value
        if (isset($data['existing'])) {
            $existing = $data['existing'];
            $value = explode(',', $existing);
            $value[] = $obj->ID;
        } else {
            $value = $obj->ID;
        }

        $this->owner->setValue($value);
        if (!$this->owner->getForm()) {
            $this->owner->setForm($form);
        }
        return $this->owner->FieldHolder();
    }

    /**
     * @return boolean
     */
    public function getIsFrontend()
    {
        return $this->isFrontend;
    }
}
