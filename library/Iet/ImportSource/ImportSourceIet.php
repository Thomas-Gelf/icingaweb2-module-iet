<?php

namespace Icinga\Module\Iet\ImportSource;

use Exception;
use Icinga\Exception\ConfigurationError;
use Icinga\Module\Director\Hook\ImportSourceHook;
use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Module\Iet\Config;

class ImportSourceIet extends ImportSourceHook
{
    protected $api;

    public function getName()
    {
        return 'iET';
    }

    /**
     * @return array
     * @throws ConfigurationError
     */
    public function fetchData()
    {
        $api = Config::getApi($this->getSetting('iet_instance'));

        return $api->fetchActiveCIsByCategory($this->getSetting('category'));
    }

// TODO: Attributes are custom, not sure how we should handle this. lookup and cache?
    public function listColumns()
    {
        return [
            'iETid',
            'Description',
            'CIOwnerGroup',
            'Status',
            'ProductionLevel',
            'Attributes',
            'Attributes.LDAP_Location',
            'Attributes.LDAP_Name',
            'Attributes.LDAP_Status',
            'Attributes.Manufacturer',
            'Attributes.Model',
            'Attributes.OPS ID',
            'Attributes.SAP ID',
            'Attributes.SAP Manufacturer ID',
            'Attributes.SAP Product ID',
            'Attributes.Serial number',
        ];
    }

    /**
     * @param QuickForm $form
     * @throws \Zend_Form_Exception
     */
    public static function addSettingsFormFields(QuickForm $form)
    {
        try {
            $ietInstances = Config::enumInstances();
            $form->addElement('select', 'iet_instance', [
                'label' => $form->translate('iET Instance'),
                'multiOptions' => $ietInstances,
                'required' => true,
                'class'    => 'autosubmit',
                'ignore'   => true,
            ]);

            $api = Config::getApi($form->getSentOrObjectSetting('iet_instance'));
        } catch (ConfigurationError $e) {
            $form->addException($e, 'iet_instance');

            return;
        }

        try {
            $categories = array_values($api->listCategories());
            $categories = array_combine($categories, $categories);
        } catch (Exception $e) {
            $form->addException($e, 'iet_instance');

            return;
        }

        $form->addElement('select', 'category', array(
            'label'       => $form->translate('CI Category'),
            'description' => $form->translate('Category to search for, like "Network Component"'),
            'multiOptions' => $categories,
            'required'    => true,
        ));
    }
}
