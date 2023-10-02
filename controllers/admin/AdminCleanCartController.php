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
include_once _PS_MODULE_DIR_ . 'prestaclean/classes/cartCleaner.php';
class AdminCleanCartController extends ModuleAdminController
{
    public function __construct()
    {
        $this->name = 'AdminCleanCart';
        $this->classname = 'CleanCart';
        $this->display = 'view';
        $this->bootstrap = true;
        parent::__construct();
        $this->meta_title = $this->l('Carts center', 'AdminCleanCartController');
        $this->toolbar_title = $this->l('Carts center', 'AdminCleanCartController');

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
        if (Tools::isSubmit('create_dummy_carts')) {
            $cartCleaner = new CartCleaner($this);
            $nb_dummy = Tools::getValue($this->module->config_name . '_NUM_DUMMY_CARTS', null) ?: 50;
            $cartCleaner->createDummyCarts($nb_dummy);
        } elseif (Tools::isSubmit('delet_carts')) {
            $cartCleaner = new CartCleaner();
            $cartCleaner->date_from = Tools::getValue($this->module->config_name . '_DATE_FROM', null);
            $cartCleaner->date_to = Tools::getValue($this->module->config_name . '_DATE_TO', null);
            $cartCleaner->shops = implode(',', Tools::getValue($this->module->config_name . '_SHOP_CARTS', []));
            $cartCleaner->deleteCarts();
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
        $status = OrderState::getOrderStates($this->context->language->id);
        $form = [];

        $orderDummyForm['form'] = [
            'legend' => [
                'title' => $this->l('Create dummy carts', 'AdminCleanCartController'),
                'icon' => 'icon-plus',
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->l('Amount of dummy carts', 'AdminCleanCartController'),
                    'desc' => $this->l('50 carts will be created by default if you let empty', 'AdminCleanCartController'),
                    'name' => $this->module->config_name . '_NUM_DUMMY_CARTS',
                    'col' => 6,
                ],
            ],
            'submit' => [
                'name' => 'create_dummy_carts',
                'title' => $this->l('Create dummy carts', 'AdminCleanCartController'),
            ],
        ];

        $orderSelectionForm['form'] = [
            'legend' => [
                'title' => $this->l('Carts delete', 'AdminCleanCartController'),
                'icon' => 'icon-trash',
            ],
            'warning' => $this->l('All carts without orders, and all others thing related to carts will be permanently erased if you let all fields empty', 'AdminCleanCartController'),
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->l('Date from', 'AdminCleanCartController'),
                    'name' => $this->module->config_name . '_DATE_FROM',
                    'class' => 'date_time',
                    'col' => 6,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Date to', 'AdminCleanCartController'),
                    'name' => $this->module->config_name . '_DATE_TO',
                    'class' => 'date_time',
                    'col' => 6,
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('Shop', 'AdminCleanCartController'),
                    'name' => $this->module->config_name . '_SHOP_CARTS[]',
                    'id' => 'select-shops',
                    'multiple' => true,
                    'options' => [
                        'query' => $shops,
                        'id' => 'id_shop',
                        'name' => 'name',
                    ],
                ],
            ],
            'buttons' => [
                [
                    'type' => 'button',
                    'class' => 'btn-block',
                    'js' => 'this.previousElementSibling.classList.toggle(\'hidden\');this.classList.toggle(\'hidden\')',
                    'title' => $this->l('Delete carts ?', 'AdminCleanCartController'),
                ],
            ],
            'submit' => [
                'name' => 'delet_carts',
                'class' => 'btn btn-block btn-danger hidden delete_carts_btn',
                'title' => $this->l('Delete carts !', 'AdminCleanCartController'),
            ],
        ];

        $form[] = $orderDummyForm;
        $form[] = $orderSelectionForm;

        return $form;
    }

    /**
     * Return configuration default values for the inputs
     */
    protected function getConfigFormValues()
    {
        return [
            $this->module->config_name . '_NUM_DUMMY_CARTS' => '50',
            $this->module->config_name . '_DATE_FROM' => '',
            $this->module->config_name . '_DATE_TO' => '',
            $this->module->config_name . '_SHOP_CARTS[]' => '',
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
