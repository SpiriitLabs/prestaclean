<?php
/**
 * 2013-2022 Spiriit
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    In Spiriit <tech@inspiriit.io>
 *  @copyright 2013-2023 In Spiriit
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */
include_once _PS_MODULE_DIR_ . 'prestaclean/classes/customerCleaner.php';
class AdminCleanCustomerController extends ModuleAdminController
{
    public function __construct()
    {
        $this->name = 'AdminCleanCustomer';
        $this->classname = 'CleanCustomer';
        $this->display = 'view';
        $this->bootstrap = true;
        parent::__construct();
        $this->meta_title = $this->l('Customers center', 'AdminCleanCustomerController');
        $this->toolbar_title = $this->l('Customers center', 'AdminCleanCustomerController');

        // If module is not active, then return
        if (!$this->module->active) {
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminDashboard'));
        }
    }

    public function init()
    {
        parent::init();
    }

    /**
     * Render view of controller
     */
    public function renderView()
    {
        $prestaCleanHelper = new PrestaCleanHelper($this->module);

        $smarty_vars = [
            'controller' => $this->classname,
            'form' => $this->renderForm(),
        ];

        $this->context->smarty->assign($smarty_vars + $prestaCleanHelper->getControllersVars() + $prestaCleanHelper->getGeneralVars());

        return $this->context->smarty->fetch(_PS_MODULE_DIR_ . $this->module->name . '/views/templates/admin/configuration.tpl');
    }

    /**
     * Process to submit actions
     */
    public function postProcess()
    {
        if (Tools::isSubmit('create_dummy_customers')) {
            $customerCleaner = new CustomerCleaner($this);
            $nb_dummy = Tools::getValue($this->module->config_name . '_NUM_DUMMY_CUSTOMERS', null) ?: 50;
            $customerCleaner->createDummyCustomers($nb_dummy);
        } elseif (Tools::isSubmit('delete_customers')) {
            $customerCleaner = new CustomerCleaner();
            $customerCleaner->date_from = Tools::getValue($this->module->config_name . '_CUSTOMER_DATE_FROM', null);
            $customerCleaner->date_to = Tools::getValue($this->module->config_name . '_CUSTOMER_DATE_TO', null);
            $customerCleaner->shops = implode(',', Tools::getValue($this->module->config_name . '_CUSTOMER_SHOP', []));
            $customerCleaner->guest = Tools::getValue($this->module->config_name . '_CUSTOMER_GUEST', null);
            $customerCleaner->never_ordered = Tools::getValue($this->module->config_name . '_CUSTOMER_NEVER_ORDERED', null);
            $customerCleaner->deleteCustomers();
        }

        return parent::postProcess();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    public function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submit';
        $helper->currentIndex = $this->context->link->getAdminLink($this->name);
        $helper->token = Tools::getAdminTokenLite($this->name);

        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFormValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm($this->getConfigForm());
    }

    /**
     * Create the structure of form.
     */
    protected function getConfigForm()
    {
        $shops = Shop::getShops();
        $form = [];

        $customerDummyForm['form'] = [
            'legend' => [
                'title' => $this->l('Create dummy customers', 'AdminCleanCustomerController'),
                'icon' => 'icon-plus',
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->l('Amount of dummy customers', 'AdminCleanCustomerController'),
                    'desc' => $this->l('50 customers will be created by default if you let empty', 'AdminCleanCustomerController'),
                    'name' => $this->module->config_name . '_NUM_DUMMY_CUSTOMERS',
                    'col' => 6,
                ],
            ],
            'submit' => [
                'name' => 'create_dummy_customers',
                'title' => $this->l('Create dummy customers', 'AdminCleanCustomerController'),
            ],
        ];

        $CustomerSelectionForm['form'] = [
            'legend' => [
                'title' => $this->l('Customers delete', 'AdminCleanCustomerController'),
                'icon' => 'icon-trash',
            ],
            'warning' => $this->l('All customers, and all others thing related to customers will be permanently erased(like address cart, messages, details) if you let all fields empty', 'AdminCleanCustomerController'),
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->l('Date from', 'AdminCleanCustomerController'),
                    'name' => $this->module->config_name . '_CUSTOMER_DATE_FROM',
                    'class' => 'date_time',
                    'col' => 6,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Date to', 'AdminCleanCustomerController'),
                    'name' => $this->module->config_name . '_CUSTOMER_DATE_TO',
                    'class' => 'date_time',
                    'col' => 6,
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('Shop', 'AdminCleanOrderController'),
                    'name' => $this->module->config_name . '_CUSTOMER_SHOP[]',
                    'id' => 'select-shops',
                    'multiple' => true,
                    'options' => [
                        'query' => $shops,
                        'id' => 'id_shop',
                        'name' => 'name',
                    ],
                ],
                [
                    'type' => 'switch',
                    'label' => $this->l('Guest'),
                    'name' => $this->module->config_name . '_CUSTOMER_GUEST',
                    'is_bool' => true,
                    'values' => [
                        [
                            'id' => 'active_on',
                            'value' => true,
                            'label' => $this->l('Enabled'),
                        ],
                        [
                            'id' => 'active_off',
                            'value' => false,
                            'label' => $this->l('Disabled'),
                        ],
                    ],
                ],
                [
                    'type' => 'switch',
                    'label' => $this->l('Never ordered'),
                    'name' => $this->module->config_name . '_CUSTOMER_NEVER_ORDERED',
                    'is_bool' => true,
                    'values' => [
                        [
                            'id' => 'active_on',
                            'value' => true,
                            'label' => $this->l('Enabled'),
                        ],
                        [
                            'id' => 'active_off',
                            'value' => false,
                            'label' => $this->l('Disabled'),
                        ],
                    ],
                ],
            ],
            'buttons' => [
                [
                    'type' => 'button',
                    'class' => 'btn-block',
                    'js' => 'this.previousElementSibling.classList.toggle(\'hidden\');this.classList.toggle(\'hidden\')',
                    'title' => $this->l('Delete customers ?', 'AdminCleanCustomerController'),
                ],
            ],
            'submit' => [
                'name' => 'delete_customers',
                'class' => 'btn btn-block btn-danger hidden delete_customers_btn',
                'title' => $this->l('Delete customers !', 'AdminCleanCustomerController'),
            ],
        ];

        $form[] = $customerDummyForm;
        $form[] = $CustomerSelectionForm;

        return $form;
    }

    /**
     * Return configuration default values for the inputs
     */
    protected function getConfigFormValues()
    {
        return [
            $this->module->config_name . '_NUM_DUMMY_CUSTOMERS' => '50',
            $this->module->config_name . '_CUSTOMER_DATE_FROM' => '',
            $this->module->config_name . '_CUSTOMER_DATE_TO' => '',
            $this->module->config_name . '_CUSTOMER_SHOP[]' => '',
            $this->module->config_name . '_CUSTOMER_GUEST' => '',
            $this->module->config_name . '_CUSTOMER_NEVER_ORDERED' => '',
        ];
    }

    /**
     * Load assets
     */
    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);

        Media::addJsDef([
            'confirmDeleteLang' => $this->l('I understand this action is not reversible, continue ?'),
        ]);

        $this->context->controller->addJS(_PS_MODULE_DIR_ . $this->module->name . '/views/js/plugins/tom-select.complete.min.js');
        $this->context->controller->addCSS(_PS_MODULE_DIR_ . $this->module->name . '/views/css/plugins/tom-select.default.min.css');
        $this->context->controller->addJS(_PS_MODULE_DIR_ . $this->module->name . '/views/js/' . $this->name . '.js');
    }
}
