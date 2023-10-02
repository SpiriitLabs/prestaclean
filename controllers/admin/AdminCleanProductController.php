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
include_once _PS_MODULE_DIR_ . 'prestaclean/classes/productCleaner.php';
class AdminCleanProductController extends ModuleAdminController
{
    public function __construct()
    {
        $this->name = 'AdminCleanProduct';
        $this->classname = 'CleanProduct';
        $this->display = 'view';
        $this->bootstrap = true;
        parent::__construct();
        $this->meta_title = $this->l('Products center', 'AdminCleanProductController');
        $this->toolbar_title = $this->l('Products center', 'AdminCleanProductController');

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
        if (Tools::isSubmit('create_dummy_products')) {
            $productCleaner = new ProductCleaner($this);
            $nb_dummy = Tools::getValue($this->module->config_name . '_NUM_DUMMY_PRODUCTS', null) ?: 50;
            $productCleaner->createDummyProducts($nb_dummy);
        } elseif (Tools::isSubmit('delete_products')) {
            $productCleaner = new ProductCleaner();
            $productCleaner->date_from = Tools::getValue($this->module->config_name . '_PRODUCT_DATE_FROM', null);
            $productCleaner->date_to = Tools::getValue($this->module->config_name . '_PRODUCT_DATE_TO', null);
            $productCleaner->shops = implode(',', Tools::getValue($this->module->config_name . '_PRODUCT_SHOP', []));
            $productCleaner->types = Tools::getValue($this->module->config_name . '_PRODUCT_TYPE', []);
            $productCleaner->categories = implode(',', Tools::getValue($this->module->config_name . '_PRODUCT_CATEGORIES', []));
            $productCleaner->active = Tools::getValue($this->module->config_name . '_PRODUCT_ACTIVE', null);
            $productCleaner->deleteProducts();
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

        $productDummyForm['form'] = [
            'legend' => [
                'title' => $this->l('Create dummy products', 'AdminCleanProductController'),
                'icon' => 'icon-plus',
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->l('Amount of dummy products', 'AdminCleanProductController'),
                    'desc' => $this->l('50 products will be created by default if you let empty', 'AdminCleanProductController'),
                    'name' => $this->module->config_name . '_NUM_DUMMY_PRODUCTS',
                    'col' => 6,
                ],
            ],
            'submit' => [
                'name' => 'create_dummy_products',
                'title' => $this->l('Create dummy products', 'AdminCleanProductController'),
            ],
        ];

        $productSelectionForm['form'] = [
            'legend' => [
                'title' => $this->l('Products delete', 'AdminCleanProductController'),
                'icon' => 'icon-trash',
            ],
            'warning' => $this->l('All products, and all others thing related to products will be permanently erased if you let all fields empty', 'AdminCleanProductController'),
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->l('Date from', 'AdminCleanProductController'),
                    'name' => $this->module->config_name . '_PRODUCT_DATE_FROM',
                    'class' => 'date_time',
                    'col' => 6,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Date to', 'AdminCleanProductController'),
                    'name' => $this->module->config_name . '_PRODUCT_DATE_TO',
                    'class' => 'date_time',
                    'col' => 6,
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('Shop', 'AdminCleanOrderController'),
                    'name' => $this->module->config_name . '_PRODUCT_SHOP[]',
                    'id' => 'select-shops',
                    'multiple' => true,
                    'options' => [
                        'query' => $shops,
                        'id' => 'id_shop',
                        'name' => 'name',
                    ],
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('Type', 'AdminCleanOrderController'),
                    'name' => $this->module->config_name . '_PRODUCT_TYPE[]',
                    'id' => 'select-types',
                    'multiple' => true,
                    'options' => [
                        'query' => [
                            [
                                'type' => 'standard',
                                'name' => $this->l('Standard', 'AdminCleanProductController'),
                            ],
                            [
                                'type' => 'combinations',
                                'name' => $this->l('Combination', 'AdminCleanProductController'),
                            ],
                            [
                                'type' => 'pack',
                                'name' => $this->l('Pack', 'AdminCleanProductController'),
                            ],
                            [
                                'type' => 'virtual',
                                'name' => $this->l('Virtual', 'AdminCleanProductController'),
                            ],
                        ],
                        'id' => 'type',
                        'name' => 'name',
                    ],
                ],
                [
                    'type' => 'categories',
                    'label' => $this->l('Categories', 'AdminCleanOrderController'),
                    'name' => $this->module->config_name . '_PRODUCT_CATEGORIES[]',
                    'tree' => [
                        'root_category' => (int) Category::getRootCategory()->id,
                        'id' => 'id_category',
                        'name' => 'name_category',
                        'use_checkbox' => true,
                        'use_search' => true,
                    ],
                ],
                [
                    'type' => 'switch',
                    'label' => $this->l('Active'),
                    'name' => $this->module->config_name . '_PRODUCT_ACTIVE',
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
                    'title' => $this->l('Delete products ?', 'AdminCleanProductController'),
                ],
            ],
            'submit' => [
                'name' => 'delete_products',
                'class' => 'btn btn-block btn-danger hidden delete_products_btn',
                'title' => $this->l('Delete products !', 'AdminCleanProductController'),
            ],
        ];

        $form[] = $productDummyForm;
        $form[] = $productSelectionForm;

        return $form;
    }

    /**
     * Return configuration default values for the inputs
     */
    protected function getConfigFormValues()
    {
        return [
            $this->module->config_name . '_NUM_DUMMY_PRODUCTS' => '50',
            $this->module->config_name . '_PRODUCT_DATE_FROM' => '',
            $this->module->config_name . '_PRODUCT_DATE_TO' => '',
            $this->module->config_name . '_PRODUCT_SHOP[]' => '',
            $this->module->config_name . '_PRODUCT_TYPE[]' => '',
            $this->module->config_name . '_PRODUCT_CATEGORIES[]' => '',
            $this->module->config_name . '_PRODUCT_ACTIVE' => '',
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
