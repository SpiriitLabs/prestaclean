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
include_once _PS_MODULE_DIR_ . 'prestaclean/classes/resetCleaner.php';
class AdminCleanResetController extends ModuleAdminController
{
    public function __construct()
    {
        $this->name = 'AdminCleanReset';
        $this->classname = 'CleanReset';
        $this->display = 'view';
        $this->bootstrap = true;
        parent::__construct();
        $this->meta_title = $this->l('Reset center', 'AdminCleanResetController');
        $this->toolbar_title = $this->l('Reset center', 'AdminCleanResetController');

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
        $is_reset_confirm = Tools::getValue($this->module->config_name . '_WIPE_ALL', null);
        if (Tools::isSubmit('wipe_all') && Tools::getIsset($this->module->config_name . '_WIPE_ALL') && $is_reset_confirm) {
            $resetCleaner = new ResetCleaner($this);
            $resetCleaner->wipeAllData();
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

        $CustomerSelectionForm['form'] = [
            'legend' => [
                'title' => $this->l('Shop reset', 'AdminCleanResetController'),
                'icon' => 'icon-refresh',
            ],
            'warning' => $this->l('All customers, orders, carts, features and all others thing related will be permanently erased(like address cart, messages, details)', 'AdminCleanResetController'),
            'input' => [
                [
                    'type' => 'switch',
                    'label' => $this->l('Reset everything'),
                    'name' => $this->module->config_name . '_WIPE_ALL',
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
                    'title' => $this->l('Confirm reset ?', 'AdminCleanResetController'),
                ],
            ],
            'submit' => [
                'name' => 'wipe_all',
                'class' => 'btn btn-block btn-danger hidden wipe_btn',
                'title' => $this->l('Reset everything !', 'AdminCleanResetController'),
            ],
        ];

        $form[] = $CustomerSelectionForm;

        return $form;
    }

    /**
     * Return configuration default values for the inputs
     */
    protected function getConfigFormValues()
    {
        return [
            $this->module->config_name . '_WIPE_ALL' => false,
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

        $this->context->controller->addJS(_PS_MODULE_DIR_ . $this->module->name . '/views/js/' . $this->name . '.js');
    }
}
