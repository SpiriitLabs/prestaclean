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
class ResetCleaner
{
    private $output;
    private $lang;
    private $shop;
    private $db;
    private $context;
    private $module;

    public function __construct()
    {
        $this->context = Context::getContext();
        $this->db = Db::getInstance();
        $this->lang = $this->context->language;
        $this->shop = $this->context->shop;
        $this->module = Module::getInstanceByName('prestaclean');
    }

    /***********************************************************************************************************************************************
    * Delete everything
    ***********************************************************************************************************************************************/

    /**
     * Delete everything
     */
    public function wipeAllData()
    {
        $res = true;

        $res &= $this->db->execute('SET FOREIGN_KEY_CHECKS = 0;');
        $res &= $this->resetCatalog();
        $res &= $this->resetSalesCustomers();
        $this->clearAllCaches();
        $res &= $this->db->execute('SET FOREIGN_KEY_CHECKS = 1;');

        if ($res) {
            $this->context->controller->confirmations[] = $this->module->l('Shop data successfully wiped', 'resetCleaner');
        } else {
            $this->context->controller->errors[] = $this->module->l('An error occured will processing', 'resetCleaner');
        }
    }

    /***********************************************************************************************************************************************
    * Reset Catalog
    ***********************************************************************************************************************************************/

    /**
     * Reset Catalog & others things related like categories, features etc
     */
    private function resetCatalog()
    {
        $res = true;

        $id_home = Configuration::getMultiShopValues('PS_HOME_CATEGORY');
        $id_root = Configuration::getMultiShopValues('PS_ROOT_CATEGORY');

        $res &= $this->db->delete('category', 'id_category NOT IN (' . pSQL(implode(',', array_map('intval', $id_home))) . ', ' . pSQL(implode(',', array_map('intval', $id_root))) . ')');
        $res &= $this->db->delete('category_lang', 'id_category NOT IN (' . pSQL(implode(',', array_map('intval', $id_home))) . ', ' . pSQL(implode(',', array_map('intval', $id_root))) . ')');
        $res &= $this->db->delete('category_shop', 'id_category NOT IN (' . pSQL(implode(',', array_map('intval', $id_home))) . ', ' . pSQL(implode(',', array_map('intval', $id_root))) . ')');
        $res &= $this->db->delete('category_group', 'id_category NOT IN (' . pSQL(implode(',', array_map('intval', $id_home))) . ', ' . pSQL(implode(',', array_map('intval', $id_root))) . ')');

        $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . 'category');
        $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . 'category_lang');
        $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . 'category_shop');
        $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . 'category_group');

        $res &= $this->db->execute('ALTER TABLE `' . _DB_PREFIX_ . 'category` AUTO_INCREMENT = ' . pSQL(1 + max(array_merge($id_home, $id_root))));
        foreach (scandir(_PS_CAT_IMG_DIR_) as $dir) {
            if (preg_match('/^[0-9]+(\-(.*))?\.jpg$/', $dir)) {
                unlink(_PS_CAT_IMG_DIR_ . $dir);
            }
        }

        $tables = $this->getCatalogRelatedTables();
        foreach ($tables as $table) {
            $res &= $this->db->execute('TRUNCATE TABLE `' . _DB_PREFIX_ . bqSQL($table) . '`');
            $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . bqSQL($table));
        }

        $res &= $this->db->delete('address', 'id_manufacturer > 0 OR id_supplier > 0 OR id_warehouse > 0');
        $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . 'address');

        Image::deleteAllImages(_PS_PROD_IMG_DIR_);
        if (!file_exists(_PS_PROD_IMG_DIR_)) {
            mkdir(_PS_PROD_IMG_DIR_);
        }

        foreach (scandir(_PS_MANU_IMG_DIR_) as $dir) {
            if (preg_match('/^[0-9]+(\-(.*))?\.jpg$/', $dir)) {
                unlink(_PS_MANU_IMG_DIR_ . $dir);
            }
        }

        foreach (scandir(_PS_SUPP_IMG_DIR_) as $dir) {
            if (preg_match('/^[0-9]+(\-(.*))?\.jpg$/', $dir)) {
                unlink(_PS_SUPP_IMG_DIR_ . $dir);
            }
        }

        return $res;
    }

    /**
     * Catalog related tables
     */
    private function getCatalogRelatedTables()
    {
        return [
            'product',
            'product_shop',
            'product_lang',
            'category_product',
            'product_tag',
            'tag',
            'image',
            'image_lang',
            'image_shop',
            'product_carrier',
            'cart_product',
            'product_attachment',
            'product_country_tax',
            'product_download',
            'product_group_reduction_cache',
            'product_sale',
            'product_supplier',
            'warehouse_product_location',
            'supply_order_detail',
            'attribute',
            'attribute_impact',
            'attribute_lang',
            'attribute_group',
            'attribute_group_lang',
            'attribute_group_shop',
            'attribute_shop',
            'product_attribute',
            'product_attribute_shop',
            'product_attribute_combination',
            'product_attribute_image',
            'manufacturer',
            'manufacturer_lang',
            'manufacturer_shop',
            'supplier',
            'supplier_lang',
            'supplier_shop',
            'customization',
            'customization_field',
            'customization_field_lang',
            'customized_data',
            'feature',
            'feature_lang',
            'feature_product',
            'feature_shop',
            'feature_value',
            'feature_value_lang',
            'pack',
            'search_index',
            'search_word',
            'specific_price',
            'specific_price_priority',
            'specific_price_rule',
            'specific_price_rule_condition',
            'specific_price_rule_condition_group',
            'stock',
            'stock_available',
            'stock_mvt',
            'warehouse',
        ];
    }

    /***********************************************************************************************************************************************
    * Reset Customers & sales
    ***********************************************************************************************************************************************/

    /**
     * Reset sales & customers
     */
    private function resetSalesCustomers()
    {
        $res = true;
        $tables = $this->getSalesRelatedTables();

        $modules_tables = [
            'sekeywords' => ['sekeyword'],
            'pagesnotfound' => ['pagenotfound'],
            'paypal' => ['paypal_customer', 'paypal_order'],
        ];

        foreach ($modules_tables as $name => $module_tables) {
            if (Module::isInstalled($name)) {
                $tables = array_merge($tables, $module_tables);
            }
        }

        foreach ($tables as $table) {
            $res &= $this->db->execute('TRUNCATE TABLE `' . _DB_PREFIX_ . bqSQL($table) . '`');
            $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . bqSQL($table));
        }

        $res &= $this->db->delete('address', 'id_customer > 0');
        $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . 'address');
        $res &= $this->db->update('employee', ['id_last_order' => 0, 'id_last_customer_message' => 0, 'id_last_customer' => 0]);

        return $res;
    }

    /**
     * Sales & Customers related tables
     */
    private function getSalesRelatedTables()
    {
        return [
            'customer',
            'cart',
            'cart_product',
            'connections',
            'connections_page',
            'connections_source',
            'customer_group',
            'customer_message',
            'customer_message_sync_imap',
            'customer_thread',
            'guest',
            'mail',
            'message',
            'message_readed',
            'orders',
            'order_carrier',
            'order_cart_rule',
            'order_detail',
            'order_detail_tax',
            'order_history',
            'order_invoice',
            'order_invoice_payment',
            'order_invoice_tax',
            'order_message',
            'order_message_lang',
            'order_payment',
            'order_return',
            'order_return_detail',
            'order_slip',
            'order_slip_detail',
            'page',
            'page_type',
            'page_viewed',
            'product_sale',
            'referrer_cache',
        ];
    }

    /***********************************************************************************************************************************************
    * Reset Caches
    ***********************************************************************************************************************************************/

    /**
     * Reset & Clean all caches
     */
    protected static function clearAllCaches()
    {
        $index = file_exists(_PS_TMP_IMG_DIR_ . 'index.php') ? Tools::file_get_contents(_PS_TMP_IMG_DIR_ . 'index.php') : '';
        Tools::deleteDirectory(_PS_TMP_IMG_DIR_, false);
        file_put_contents(_PS_TMP_IMG_DIR_ . 'index.php', $index);
        Context::getContext()->smarty->clearAllCache();
    }
}
