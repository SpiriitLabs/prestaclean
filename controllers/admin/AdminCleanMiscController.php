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
include_once _PS_MODULE_DIR_ . 'prestaclean/classes/miscCleaner.php';
class AdminCleanMiscController extends ModuleAdminController
{
    public function __construct()
    {
        $this->name = 'AdminCleanMisc';
        $this->classname = 'CleanMisc';
        $this->display = 'view';
        $this->bootstrap = true;
        parent::__construct();
        $this->meta_title = $this->l('Maintenance & Optimization', 'AdminCleanMiscController');
        $this->toolbar_title = $this->l('Maintenance & Optimization', 'AdminCleanMiscController');

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
        $miscCleaner = new MiscCleaner($this);

        if (Tools::isSubmit('clean_emails')) {
            $date_from = Tools::getValue($this->module->config_name . '_EMAIL_DATE_FROM', null);
            $date_to = Tools::getValue($this->module->config_name . '_EMAIL_DATE_TO', null);
            $miscCleaner->cleanEmails($date_from, $date_to);
        } elseif (Tools::isSubmit('clean_optimize')) {
            $miscCleaner->cleanOptimize = Tools::getValue($this->module->config_name . '_CLEAN_OPTIMIZE', null);
            $miscCleaner->cleanStatsAndLogs = Tools::getValue($this->module->config_name . '_CLEAN_STATS_LOGS', null);
            $miscCleaner->fixIntegrity = Tools::getValue($this->module->config_name . '_FIX_INTEGRITY', null);
            $miscCleaner->cleanLogsFiles = Tools::getValue($this->module->config_name . '_CLEAN_LOGS_IMG_FILES', null);
            $miscCleaner->processCleanOptimize();
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
        $form = [];

        $emailsForm['form'] = [
            'legend' => [
                'title' => $this->l('Clean emails', 'AdminCleanMiscController'),
                'icon' => 'icon-envelope',
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->l('Date from', 'AdminCleanMiscController'),
                    'name' => $this->module->config_name . '_EMAIL_DATE_FROM',
                    'class' => 'date_time',
                    'col' => 6,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Date to', 'AdminCleanMiscController'),
                    'name' => $this->module->config_name . '_EMAIL_DATE_TO',
                    'class' => 'date_time',
                    'col' => 6,
                ],
            ],
            'buttons' => [
                [
                    'type' => 'button',
                    'class' => 'btn-block',
                    'js' => 'this.previousElementSibling.classList.toggle(\'hidden\');this.classList.toggle(\'hidden\')',
                    'title' => $this->l('Clean emails ?', 'AdminCleanMiscController'),
                ],
            ],
            'submit' => [
                'name' => 'clean_emails',
                'class' => 'btn btn-block btn-danger hidden clean_btn',
                'title' => $this->l('Clean emails !', 'AdminCleanMiscController'),
            ],
        ];

        $cleanOptimizeForm['form'] = [
            'legend' => [
                'title' => $this->l('Clean & Optimize', 'AdminCleanMiscController'),
                'icon' => 'icon-magic',
            ],
            'warning' => $this->l('Its STRONGLY RECOMMENDED to BACKUP your database before doing any action below', 'AdminCleanMiscController'),
            'input' => [
                [
                    'type' => 'switch',
                    'label' => $this->l('Carts & Admin tabs otimization'),
                    'desc' => $this->l('Carts not ordered and older than 1 month, carts rules expired, disabled, out of quantity older than 1 month will be deleted, admin tabs will be indexed'),
                    'name' => $this->module->config_name . '_CLEAN_OPTIMIZE',
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
                    'label' => $this->l('Clean Stats & Logs'),
                    'desc' => $this->l('This option will empty all tables collecting miscellaneous data about traffic, pages viewed, guest... You may be disconnected after.'),
                    'name' => $this->module->config_name . '_CLEAN_STATS_LOGS',
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
                    'label' => $this->l('Check & Fix integrity'),
                    'desc' => $this->l('This option will run queries to fix broken data, double configuration or inexisting lang configuration, orphans tables associations entries'),
                    'name' => $this->module->config_name . '_FIX_INTEGRITY',
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
                    'label' => $this->l('Clean files & Caches'),
                    'desc' => $this->l('This option will clean your Prestashop logs directory, img temporary dir & caches'),
                    'name' => $this->module->config_name . '_CLEAN_LOGS_IMG_FILES',
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
                    'title' => $this->l('Clean & Optimize ?', 'AdminCleanMiscController'),
                ],
            ],
            'submit' => [
                'name' => 'clean_optimize',
                'class' => 'btn btn-block btn-danger hidden clean_btn',
                'title' => $this->l('Clean & Optimize !', 'AdminCleanMiscController'),
            ],
        ];

        $form[] = $emailsForm;
        $form[] = $cleanOptimizeForm;

        return $form;
    }

    /**
     * Return configuration default values for the inputs
     */
    protected function getConfigFormValues()
    {
        return [
            $this->module->config_name . '_EMAIL_DATE_FROM' => '',
            $this->module->config_name . '_EMAIL_DATE_TO' => '',
            $this->module->config_name . '_CLEAN_OPTIMIZE' => '',
            $this->module->config_name . '_CLEAN_STATS_LOGS' => '',
            $this->module->config_name . '_FIX_INTEGRITY' => '',
            $this->module->config_name . '_CLEAN_LOGS_IMG_FILES' => '',
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
