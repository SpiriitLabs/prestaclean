<?php
/**
 * 2013-2023 Spiriit
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
class OrderCleaner
{
    private $output;
    private $lang;
    private $shop;
    private $db;
    private $context;
    private $module;
    public $date_from;
    public $date_to;
    public $shops;
    public $status;

    public function __construct()
    {
        $this->context = Context::getContext();
        $this->db = Db::getInstance();
        $this->lang = $this->context->language;
        $this->shop = $this->context->shop;
        $this->module = Module::getInstanceByName('prestaclean');
    }

    /**
     * Return array of orders related tables
     *
     * @return array
     */
    private static function getOrdersRelatedTables()
    {
        return [
            'customer_thread',
            'message',
            'orders',
            'order_carrier',
            'order_cart_rule',
            'order_detail',
            'order_history',
            'order_invoice',
            'order_return',
            'order_slip',
        ];
    }

    /***********************************************************************************************************************************************
    * Delete
    ***********************************************************************************************************************************************/

    /**
     * Delete orders by ids given or by conditions
     *
     * @param $id_orders
     */
    public function deleteOrders($id_orders = null)
    {
        if ($id_orders === null || !is_array($id_orders)) {
            $sql = new DbQuery();
            $sql->select('id_order');
            $sql->from('orders', 'o');
            if ($this->date_from && $this->date_to) {
                $date_from = DateTime::createFromFormat('!d/m/Y H:i', $this->date_from)->getTimestamp();
                $date_to = DateTime::createFromFormat('!d/m/Y H:i', $this->date_to)->getTimestamp();

                $sql->where('UNIX_TIMESTAMP(o.date_add) BETWEEN ' . pSQL($date_from) . ' AND ' . pSQL($date_to));
            }
            if ($this->status) {
                $sql->where('o.current_state IN (' . pSQL($this->status) . ')');
            }
            if ($this->shops) {
                $sql->where('o.id_shop IN (' . pSQL($this->shops) . ')');
            }
            $sql->orderBy('o.id_order');

            $id_orders = $this->db->executeS($sql) ?? [];
            $id_orders = array_column($id_orders, 'id_order');
        }

        if (empty($id_orders)) {
            $this->context->controller->confirmations[] = $this->module->l('Nothing to delete', 'orderCleaner');

            return;
        }

        $ordersDelete = $this->processDelete($id_orders) && $this->cleanOrphans();

        if ($ordersDelete) {
            $logs = '';
            $this->context->controller->confirmations[] = $this->module->l('Success!', 'orderCleaner');
            foreach ($this->output as $table => $str) {
                $logs .= '<br/>' . sprintf($this->module->l('%s: %d.'), $table, $str);
            }
            $this->context->controller->confirmations[] = $logs;

            return;
        }

        $this->context->controller->errors[] = $this->module->l('An error occured will processing', 'orderCleaner');
    }

    /**
     * Process delete orders & related tables data's
     *
     * @param $id_orders
     * @return bool $res
     */
    public function processDelete($id_orders)
    {
        $tables = self::getOrdersRelatedTables();
        $res = true;

        foreach ($tables as $table) {
            if ($table == 'orders') {
                $res &= $this->db->delete('order_payment', 'order_reference IN (SELECT reference FROM ' . _DB_PREFIX_ . bqSQL($table) . ' WHERE id_order IN (' . bqSQL(implode(',', $id_orders)) . '))');
                $this->output['order_payment'] = $this->db->numRows();
                $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . 'order_payment');
                $res &= $this->db->delete('cart', 'id_cart IN (SELECT id_cart FROM ' . _DB_PREFIX_ . bqSQL($table) . ' WHERE id_order IN (' . bqSQL(implode(',', $id_orders)) . '))');
                $this->output['cart'] = $this->db->numRows();
                $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . 'cart');
                $res &= $this->db->delete('cart_product', 'id_cart IN (SELECT id_cart FROM ' . _DB_PREFIX_ . bqSQL($table) . ' WHERE id_order IN (' . bqSQL(implode(',', $id_orders)) . '))');
                $this->output['cart_product'] = $this->db->numRows();
                $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . 'cart_product');
            } elseif ($table == 'order_detail') {
                $res &= $this->db->delete('order_detail_tax', 'id_order_detail IN (SELECT id_order_detail FROM ' . _DB_PREFIX_ . bqSQL($table) . ' WHERE id_order IN (' . bqSQL(implode(',', $id_orders)) . '))');
                $this->output['order_detail_tax'] = $this->db->numRows();
                $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . 'order_detail_tax');
            } elseif ($table == 'order_invoice') {
                $res &= $this->db->delete('order_invoice_payment', 'id_order_invoice IN (SELECT id_order_invoice FROM ' . _DB_PREFIX_ . bqSQL($table) . ' WHERE id_order IN (' . bqSQL(implode(',', $id_orders)) . '))');
                $this->output['order_invoice_payment'] = $this->db->numRows();
                $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . 'order_invoice_payment');
                $res &= $this->db->delete('order_invoice_tax', 'id_order_invoice IN (SELECT id_order_invoice FROM ' . _DB_PREFIX_ . bqSQL($table) . ' WHERE id_order IN (' . bqSQL(implode(',', $id_orders)) . '))');
                $this->output['order_invoice_tax'] = $this->db->numRows();
                $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . 'order_invoice_tax');
            } elseif ($table == 'order_return') {
                $res &= $this->db->delete('order_return_detail', 'id_order_return IN (SELECT id_order_return FROM ' . _DB_PREFIX_ . bqSQL($table) . ' WHERE id_order IN (' . bqSQL(implode(',', $id_orders)) . '))');
                $this->output['order_return_detail'] = $this->db->numRows();
                $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . 'order_return_detail');
            } elseif ($table == 'order_slip') {
                $res &= $this->db->delete('order_slip_detail', 'id_order_slip IN (SELECT id_order_slip FROM ' . _DB_PREFIX_ . bqSQL($table) . ' WHERE id_order IN (' . bqSQL(implode(',', $id_orders)) . '))');
                $this->output['order_slip_detail'] = $this->db->numRows();
                $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . 'order_slip_detail');
            } elseif ($table == 'message') {
                $res &= $this->db->delete('message_readed', 'id_message IN (SELECT id_message FROM ' . _DB_PREFIX_ . bqSQL($table) . ' WHERE id_order IN (' . bqSQL(implode(',', $id_orders)) . '))');
                $this->output['message_readed'] = $this->db->numRows();
                $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . 'message_readed');
            }

            $res &= $this->db->delete(bqSQL($table), 'id_order IN (' . bqSQL(implode(',', $id_orders)) . ')');
            $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . bqSQL($table));

            $this->output[$table] = $this->db->numRows();
        }

        return $res;
    }

    /***********************************************************************************************************************************************
    * Create Dummies
    ***********************************************************************************************************************************************/

    /**
     * Create dummy orders
     * @param $nb
     */
    public function createDummyOrders($nb = 50)
    {
        for ($o = 1; $o <= $nb; ++$o) {
            $customer_id = Db::getInstance()->getValue('SELECT id_customer FROM `' . _DB_PREFIX_ . 'customer` ORDER BY RAND()');
            $address_id = Db::getInstance()->getValue('SELECT id_address FROM `' . _DB_PREFIX_ . 'address` ORDER BY RAND()');
            $carrier_id = Db::getInstance()->getValue('SELECT id_carrier FROM `' . _DB_PREFIX_ . 'carrier` ORDER BY RAND()');
            $state_id = Db::getInstance()->getValue('SELECT id_order_state FROM `' . _DB_PREFIX_ . 'order_state` ORDER BY RAND()');

            if (empty($customer_id) || empty($address_id) || empty($carrier_id) || empty($state_id)) {
                $this->context->controller->errors[] = $this->module->l('No enought base data to make dummy orders(customers, address, carriers or order status)', 'orderCleaner');

                return;
            }

            // Cart information
            $new_cart = new Cart();
            $new_cart->id_customer = (int) $customer_id;
            $new_cart->id_address_delivery = (int) $address_id;
            $new_cart->id_address_invoice = $new_cart->id_address_delivery;
            $new_cart->id_lang = (int) Configuration::get('PS_LANG_DEFAULT');
            $new_cart->id_currency = Context::getContext()->currency->id;
            $new_cart->id_carrier = (int) $carrier_id;

            $new_cart->add();

            for ($p = 1; $p <= 5; ++$p) {
                $result = $new_cart->updateQty(rand(1, 5), $p);
            }

            // Creating order from cart
            $shop = Context::getContext()->shop;
            if (!Validate::isLoadedObject($shop)) {
                $shop = new Shop((int) Configuration::get('PS_SHOP_DEFAULT'));
            }
            // Important to setContext
            Shop::setContext($shop::CONTEXT_SHOP, $shop->id);
            $this->context->shop = $shop;
            $this->context->cookie->id_shop = $shop->id;
            $payment_module = Module::getInstanceByName('ps_wirepayment');
            $result = $payment_module->validateOrder($new_cart->id, Configuration::get('PS_OS_BANKWIRE'), $new_cart->getOrderTotal(), 'Credit card', 'Test');

            // Get the order id after creating it from the cart.
            $id_order = Order::getOrderByCartId($new_cart->id);

            $history = new OrderHistory();
            $history->id_order = (int) $id_order;
            $history->changeIdOrderState((int) $state_id, (int) $history->id_order);
            $history->save();
        }

        Context::getContext()->controller->confirmations[] = sprintf('%d order(s) successfully created.', $nb);
    }

    /***********************************************************************************************************************************************
    * Clean Orphans
    ***********************************************************************************************************************************************/

    /**
     * Clean orphans orders on related tables
     */
    public function cleanOrphans()
    {
        $tables = self::getOrdersRelatedTables();
        $res = true;
        $this->output[$this->module->l('Orphans cleaned')] = 0;
        foreach ($tables as $table) {
            if ($table == 'orders') {
                $res &= Db::getInstance()->delete('order_payment', 'order_reference NOT IN (SELECT reference FROM ' . _DB_PREFIX_ . bqSQL($table) . ')');
                $this->output[$this->module->l('Orphans cleaned')] += $this->db->numRows();
                $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . 'order_payment');
                $res &= Db::getInstance()->delete('cart_product', 'id_cart NOT IN (SELECT id_cart FROM ' . _DB_PREFIX_ . 'cart)');
                $this->output[$this->module->l('Orphans cleaned')] += $this->db->numRows();
                $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . 'cart_product');
                continue;
            } elseif ($table == 'order_detail') {
                $res &= Db::getInstance()->delete('order_detail_tax', 'id_order_detail NOT IN (SELECT id_order_detail FROM ' . _DB_PREFIX_ . bqSQL($table) . ')');
                $this->output[$this->module->l('Orphans cleaned')] += $this->db->numRows();
                $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . 'order_detail_tax');
            } elseif ($table == 'order_invoice') {
                $res &= Db::getInstance()->delete('order_invoice_payment', 'id_order_invoice NOT IN (SELECT id_order_invoice FROM ' . _DB_PREFIX_ . bqSQL($table) . ')');
                $this->output[$this->module->l('Orphans cleaned')] += $this->db->numRows();
                $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . 'order_invoice_payment');
                $res &= Db::getInstance()->delete('order_invoice_tax', 'id_order_invoice NOT IN (SELECT id_order_invoice FROM ' . _DB_PREFIX_ . bqSQL($table) . ')');
                $this->output[$this->module->l('Orphans cleaned')] += $this->db->numRows();
                $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . 'order_invoice_tax');
            } elseif ($table == 'order_return') {
                $res &= Db::getInstance()->delete('order_return_detail', 'id_order_return NOT IN (SELECT id_order_return FROM ' . _DB_PREFIX_ . bqSQL($table) . ')');
                $this->output[$this->module->l('Orphans cleaned')] += $this->db->numRows();
                $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . 'order_return_detail');
            } elseif ($table == 'order_slip') {
                $res &= Db::getInstance()->delete('order_slip_detail', 'id_order_slip NOT IN (SELECT id_order_slip FROM ' . _DB_PREFIX_ . bqSQL($table) . ')');
                $this->output[$this->module->l('Orphans cleaned')] += $this->db->numRows();
                $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . 'order_slip_detail');
            } elseif ($table == 'message') {
                $res &= Db::getInstance()->delete('message_readed', 'id_message NOT IN (SELECT id_message FROM ' . _DB_PREFIX_ . bqSQL($table) . ')');
                $this->output[$this->module->l('Orphans cleaned')] += $this->db->numRows();
                $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . 'message_readed');
            }

            $res &= Db::getInstance()->delete(bqSQL($table), 'id_order NOT IN (SELECT id_order FROM ' . _DB_PREFIX_ . 'orders)');
            $this->output[$this->module->l('Orphans cleaned')] += $this->db->numRows();
            $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . bqSQL($table));
        }

        return $res;
    }
}
