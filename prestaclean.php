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
if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShopBundle\Controller\Admin\Sell\Order\ActionsBarButton;

include_once _PS_MODULE_DIR_ . 'prestaclean/classes/prestaCleanHelper.php';

class PrestaClean extends Module
{
    public $config_name;

    public function __construct()
    {
        $this->name = 'prestaclean';
        $this->tab = 'administration';
        $this->version = '1.0.2';
        $this->author = 'Spiriit';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Prestashop Cleaner and Maintenance toolkit');
        $this->description = $this->l('Keep your Prestashop database and files clean, get out of old logs, clean up unwanted orders and more');

        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];

        $this->config_name = strtoupper($this->name);
    }

    public function install()
    {
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        return parent::install()
        && $this->registerHook('actionAdminControllerSetMedia')
        && $this->registerHook('actionGetAdminOrderButtons');
    }

    public function uninstall()
    {
        return parent::uninstall();
    }

    /**
     * Redirect to main controller on Configure
     */
    public function getContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminCleanMisc'));
    }

    /***********************************************************************************************************************************************
    * Hooks
    ***********************************************************************************************************************************************/

    /**
     * hookActionAdminControllerSetMedia set js variables & load css/js needed on Back
     */
    public function hookActionAdminControllerSetMedia()
    {
        if (Tools::getValue('controller') !== 'AdminOrders' && empty(Tools::getValue('id_order'))) {
            return;
        }

        Media::addJsDef([
            'confirmDeleteLang' => $this->l('I understand this action is not reversible, continue ?'),
        ]);

        $this->context->controller->addJS($this->_path . 'views/js/AdminOrders.js');
    }

    /**
     * hookActionGetAdminOrderButtons, add buttons to main buttons bar on order page
     */
    public function hookActionGetAdminOrderButtons(array $params)
    {
        if (Validate::isLoadedObject($order = new Order($params['id_order']))) {
            /** @var \Symfony\Bundle\FrameworkBundle\Routing\Router $router */
            $router = $this->get('router');
            /** @var \PrestaShopBundle\Controller\Admin\Sell\Order\ActionsBarButtonsCollection $bar */
            $bar = $params['actions_bar_buttons_collection'];
            $ordersUrl = $router->generate('admin_orders_index');
            $viewOrderUrl = $router->generate('admin_orders_view', ['orderId' => (int) $order->id]);
            $deleteOrderUrl = $viewOrderUrl . '&delete_order=1';
            $bar->add(
                new ActionsBarButton(
                    'btn-danger delete-order',
                    ['href' => $deleteOrderUrl],
                    "<i class='material-icons' aria-hidden='true'>delete</i> " . $this->trans('Delete order', [], 'Shop.Theme.Actions')
                )
            );

            // This process the button click event
            if (Tools::getValue('delete_order')) {
                $orderCleaner = new OrderCleaner();
                $deleteResult = $orderCleaner->deleteOrders([$order->id]);

                if ($deleteResult == true) {
                    $this->get('session')->getFlashBag()->add(
                        'success',
                        $this->l('Order successfully deleted')
                    );
                } else {
                    $this->get('session')->getFlashBag()->add(
                        'error',
                        $this->l('There was an error while deleting order.')
                    );
                }

                Tools::redirectAdmin($ordersUrl);
            }
        }
    }
}
