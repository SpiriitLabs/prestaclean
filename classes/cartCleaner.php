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
class CartCleaner
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

    public function __construct()
    {
        $this->context = Context::getContext();
        $this->db = Db::getInstance();
        $this->lang = $this->context->language;
        $this->shop = $this->context->shop;
        $this->module = Module::getInstanceByName('prestaclean');
    }

    /**
     * Return array of cart related tables
     *
     * @return array
     */
    private static function getCartsRelatedTables()
    {
        return [
            'cart',
            'cart_product',
        ];
    }

    /***********************************************************************************************************************************************
    * Delete
    ***********************************************************************************************************************************************/

    /**
     * Delete carts by ids given or by conditions
     *
     * @param $id_carts
     */
    public function deleteCarts($id_carts = null)
    {
        if ($id_carts === null || !is_array($id_carts)) {
            $sql = new DbQuery();
            $sql->select('id_cart');
            $sql->from('cart', 'c');
            if ($this->date_from && $this->date_to) {
                $date_from = DateTime::createFromFormat('!d/m/Y H:i', $this->date_from)->getTimestamp();
                $date_to = DateTime::createFromFormat('!d/m/Y H:i', $this->date_to)->getTimestamp();

                $sql->where('UNIX_TIMESTAMP(c.date_add) BETWEEN ' . pSQL($date_from) . ' AND ' . pSQL($date_to));
            }
            if ($this->shops) {
                $sql->where('c.id_shop IN (' . pSQL($this->shops) . ')');
            }
            $sql->where('id_cart NOT IN (SELECT id_cart FROM ' . _DB_PREFIX_ . 'orders)');
            $sql->orderBy('c.id_cart');

            $id_carts = $this->db->executeS($sql) ?? [];
            $id_carts = array_column($id_carts, 'id_cart');
        }

        if (empty($id_carts)) {
            $this->context->controller->confirmations[] = $this->module->l('Nothing to delete', 'cartCleaner');

            return;
        }

        $cartsDelete = $this->processDelete($id_carts) && $this->cleanOrphans();

        if ($cartsDelete) {
            $logs = '';
            $this->context->controller->confirmations[] = $this->module->l('Success!', 'cartCleaner');
            foreach ($this->output as $table => $str) {
                $logs .= '<br/>' . sprintf($this->module->l('%s: %d.'), $table, $str);
            }
            $this->context->controller->confirmations[] = $logs;

            return;
        }

        $this->context->controller->errors[] = $this->module->l('An error occured will processing', 'cartCleaner');
    }

    /**
     * Process delete carts & related tables data's
     *
     * @param $id_carts
     * @return bool $res
     */
    public function processDelete($id_carts)
    {
        $tables = self::getCartsRelatedTables();
        $res = true;
        $carts_list = implode(',', array_map('intval', $id_carts));

        foreach ($tables as $table) {
            $res &= $this->db->delete(bqSQL($table), 'id_cart IN (' . pSQL($carts_list) . ')');
            if ($affected_rows = $this->db->Affected_Rows()) {
                $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . bqSQL($table));
                $this->output[$table] = (int) $affected_rows;
            }
            $this->output[$table] = (int) $this->db->numRows();
        }

        return $res;
    }

    /***********************************************************************************************************************************************
    * Create Dummies
    ***********************************************************************************************************************************************/

    /**
     * Create dummy carts
     * @param $nb
     */
    public function createDummyCarts($nb = 50)
    {
        for ($i = 1; $i <= $nb; ++$i) {
            $customer_id = Db::getInstance()->getValue('SELECT id_customer FROM `' . _DB_PREFIX_ . 'customer` ORDER BY RAND()');
            $address_id = Db::getInstance()->getValue('SELECT id_address FROM `' . _DB_PREFIX_ . 'address` ORDER BY RAND()');
            $carrier_id = Db::getInstance()->getValue('SELECT id_carrier FROM `' . _DB_PREFIX_ . 'carrier` ORDER BY RAND()');

            if (empty($customer_id) || empty($address_id) || empty($carrier_id)) {
                $this->context->controller->errors[] = $this->module->l('No enought base data to make dummy carts(customers, address, carriers)', 'cartCleaner');

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

            for ($x = 1; $x <= 5; ++$x) {
                $result = $new_cart->updateQty(rand(1, 5), $x);
            }
        }

        Context::getContext()->controller->confirmations[] = $this->module->l(sprintf('%d cart(s) successfully created.', $nb), 'cartCleaner');
    }

    /**
     * Clean orphans carts on related tables
     */
    public function cleanOrphans()
    {
        $res = true;
        $this->output[$this->module->l('Orphans cleaned')] = 0;

        $res &= Db::getInstance()->delete('cart_product', 'id_cart NOT IN (SELECT id_cart FROM ' . _DB_PREFIX_ . 'cart)');
        $this->output[$this->module->l('Orphans cleaned')] += (int) $this->db->numRows();
        $res &= Db::getInstance()->delete('cart', 'id_cart NOT IN (SELECT DISTINCT(id_cart) FROM ' . _DB_PREFIX_ . 'cart_product)');
        $this->output[$this->module->l('Orphans cleaned')] += (int) $this->db->numRows();

        $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . 'cart_product');
        $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . 'cart');

        return $res;
    }
}
