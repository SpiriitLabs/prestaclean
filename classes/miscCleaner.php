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
class MiscCleaner
{
    private $output;
    private $lang;
    private $shop;
    private $db;
    private $context;
    private $module;
    public $cleanOptimize;
    public $cleanStatsAndLogs;
    public $fixIntegrity;
    public $cleanLogsFiles;

    public function __construct()
    {
        $this->context = Context::getContext();
        $this->db = Db::getInstance();
        $this->lang = $this->context->language;
        $this->shop = $this->context->shop;
        $this->module = Module::getInstanceByName('prestaclean');
    }

    /***********************************************************************************************************************************************
    * Emails
    ***********************************************************************************************************************************************/

    /**
     * Clean emails
     * @param $date_from
     * @param $date_to
     */
    public function cleanEmails($date_from = null, $date_to = null)
    {
        $sqlCount = 'SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'mail';
        $productsBefore = Db::getInstance()->getValue($sqlCount);

        if ($date_from && $date_to) {
            $date_from = DateTime::createFromFormat('!d/m/Y H:i', $date_from)->getTimestamp();
            $date_to = DateTime::createFromFormat('!d/m/Y H:i', $date_to)->getTimestamp();

            $deleteQuery = $this->db->delete('mail', 'UNIX_TIMESTAMP(date_add) BETWEEN ' . pSQL($date_from) . ' AND ' . pSQL($date_to));
        } else {
            $deleteQuery = $this->db->execute('TRUNCATE ' . _DB_PREFIX_ . 'mail');
        }
        $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . 'mail');

        $productsAfter = Db::getInstance()->getValue($sqlCount);
        $nbDeleted = $productsBefore - $productsAfter;

        if ($deleteQuery) {
            $this->context->controller->confirmations[] = $this->module->l('Success!', 'miscCleaner');
            $this->context->controller->confirmations[] = sprintf($this->module->l('%s mail(s) deleted.'), $nbDeleted);

            return;
        }

        $this->context->controller->errors[] = $this->module->l('An error occured will processing', 'miscCleaner');
    }

    /***********************************************************************************************************************************************
    * Clean & Optimize
    ***********************************************************************************************************************************************/

    /**
     * Clean & Optimize
     * Clean Carts/Carts rules & optimize admin tabs
     * Clean Stats & Logs tables
     * Check & Fix DB integrity(Duped configuration, orphans in associated tables _lang/_shop)
     * Clean logs & images temporary files on server
     */
    public function processCleanOptimize()
    {
        if ($this->cleanOptimize) {
            $this->cleanAndOptimize();
        }
        if ($this->cleanStatsAndLogs) {
            $this->cleanStatsAndLogs();
        }
        if ($this->fixIntegrity) {
            $this->checkAndFixIntegrity();
        }
        if ($this->cleanLogsFiles) {
            $this->cleanLogsImgFiles();
        }

        if (!empty($this->output)) {
            $logs = '';
            $this->context->controller->confirmations[] = $this->module->l('Success!', 'miscCleaner');

            foreach ($this->output as $key => $log) {
                $logs .= '<br/>' . sprintf($this->module->l('%s: %s.'), $key, $log);
            }
            $this->context->controller->confirmations[] = $logs;
        } else {
            $this->context->controller->confirmations[] = $this->module->l('Everything is already clean, Good job !', 'miscCleaner');
        }
    }

    /**
     * Clean Carts/Carts rules & optimize admin tabs
     */
    private function cleanAndOptimize()
    {
        $queryCarts = $this->db->delete('cart', 'id_cart NOT IN (SELECT id_cart FROM `' . _DB_PREFIX_ . 'orders`) AND date_add < "' . pSQL(date('Y-m-d', strtotime('-1 month'))) . '"');
        if ($queryCarts) {
            $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . 'cart');
            if ($affected_rows = Db::getInstance()->Affected_Rows()) {
                $this->output[$this->module->l('Carts deleted', 'miscCleaner')] = $affected_rows;
            }
        }

        $queryCartsRules = $this->db->delete('cart_rule', '( active = 0 OR quantity = 0 OR date_to < "' . pSQL(date('Y-m-d')) . '" ) AND date_add < "' . pSQL(date('Y-m-d', strtotime('-1 month'))) . '"');
        if ($queryCartsRules) {
            $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . 'cart_rule');
            if ($affected_rows = $this->db->Affected_Rows()) {
                $this->output[$this->module->l('Carts rules deleted', 'miscCleaner')] = $affected_rows;
            }
        }

        $parents = $this->db->executeS('SELECT DISTINCT id_parent FROM ' . _DB_PREFIX_ . 'tab');
        foreach ($parents as $parent) {
            $sql = new DbQuery();
            $sql->select('id_tab');
            $sql->from('tab');
            $sql->where('id_parent = ' . (int) pSQL($parent['id_parent']));
            $sql->orderBy('IF(class_name IN ("AdminHome", "AdminDashboard"), 1, 2), position ASC');
            $children = Db::getInstance()->executeS($sql);

            $i = 1;
            foreach ($children as $child) {
                $queryUpdate = $this->db->update(
                    'tab',
                    [
                        'position' => pSQL($i++),
                    ],
                    'id_tab = ' . (int) pSQL($child['id_tab']) . ' AND id_parent = ' . (int) pSQL($parent['id_parent'])
                );

                if ($queryUpdate) {
                    if ($affected_rows = $this->db->Affected_Rows()) {
                        $this->output['Tab ' . $queryUpdate . ' pos'] = $affected_rows;
                    }
                }
            }
        }
    }

    /***********************************************************************************************************************************************
    * Check & Fix integrity
    ***********************************************************************************************************************************************/

    /**
     * Check & Fix DB integrity(Duped configuration, orphans in associated tables _lang/_shop)
     */
    private function checkAndFixIntegrity()
    {
        // Remove doubles in the configuration
        $i = 0;
        $filtered_configuration = [];
        $result = $this->db->executeS('SELECT * FROM ' . _DB_PREFIX_ . 'configuration');
        foreach ($result as $row) {
            $key = sprintf('%d-|-%d-|-%s', $row['id_shop_group'], $row['id_shop'], $row['name']);
            if (in_array($key, $filtered_configuration)) {
                ++$i;
                $this->db->delete('configuration', 'id_configuration = ' . (int) $row['id_configuration']);
                $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . 'configuration');
                $this->output[$this->module->l('Configurations dupes', 'miscCleaner')] = $i;
            } else {
                $filtered_configuration[] = $key;
            }
        }
        unset($filtered_configuration);

        // Remove inexisting or monolanguage configuration value from configuration_lang
        $queryDeleteConfLang = $this->db->delete('configuration_lang', '`id_configuration` NOT IN (SELECT `id_configuration` FROM `' . _DB_PREFIX_ . 'configuration`)
        OR `id_configuration` IN (SELECT `id_configuration` FROM `' . _DB_PREFIX_ . 'configuration` WHERE name IS NULL OR name = "")');
        if ($queryDeleteConfLang) {
            if ($affected_rows = $this->db->Affected_Rows()) {
                $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . 'configuration_lang');
                $this->output[$this->module->l('Configurations langs orphans', 'miscCleaner')] = $affected_rows;
            }
        }

        // Simple Cascade Delete
        $queries = $this->getCheckAndFixQueries();

        foreach ($queries as $query_array) {
            // If this is a module and the module is not installed, we continue
            if (isset($query_array[4]) && !Module::isInstalled($query_array[4])) {
                continue;
            }

            $queryDeleteOrphans = $this->db->delete(bqSQL($query_array[0]), '`' . bqSQL($query_array[1]) . '` NOT IN (SELECT `' . bqSQL($query_array[3]) . '` FROM `' . _DB_PREFIX_ . bqSQL($query_array[2]) . '`)');
            if ($queryDeleteOrphans) {
                if ($affected_rows = $this->db->Affected_Rows()) {
                    $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . bqSQL($query_array[0]));
                    $this->output['Table ' . $query_array[0]] = $affected_rows;
                }
            }
        }

        // _lang table cleaning
        $tables = $this->db->executeS('SHOW TABLES LIKE "' . preg_replace('/([%_])/', '\\$1', _DB_PREFIX_) . '%_\\_lang"');
        foreach ($tables as $table) {
            $table_lang = current($table);
            $table = str_replace('_lang', '', $table_lang);
            $id_table = 'id_' . preg_replace('/^' . _DB_PREFIX_ . '/', '', $table);

            $queryDeleteLangs = $this->db->delete('`' . bqSQL($table_lang) . '`', '`' . bqSQL($id_table) . '` NOT IN (SELECT `' . bqSQL($id_table) . '` FROM `' . bqSQL($table) . '`)');
            if ($queryDeleteLangs) {
                if ($affected_rows = $this->db->Affected_Rows()) {
                    $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . bqSQL($table_lang));
                    $this->output[$this->module->l(sprintf('Table %s, %s not in %s', $table_lang, $id_table, $table), 'miscCleaner')] = $affected_rows;
                }
            }

            $queryDeleteLangs = $this->db->delete('`' . bqSQL($table_lang) . '`', '`id_lang` NOT IN (SELECT `id_lang` FROM `' . _DB_PREFIX_ . 'lang`)');
            if ($queryDeleteLangs) {
                if ($affected_rows = $this->db->Affected_Rows()) {
                    $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . bqSQL($table_lang));
                    $this->output[$this->module->l(sprintf('Table %s, id lang not in %s', $table_lang, $table), 'miscCleaner')] = $affected_rows;
                }
            }
        }

        // _shop table cleaning
        $tables = $this->db->executeS('SHOW TABLES LIKE "' . preg_replace('/([%_])/', '\\$1', _DB_PREFIX_) . '%_\\_shop"');
        foreach ($tables as $table) {
            $table_shop = current($table);
            $table = str_replace('_shop', '', $table_shop);
            $id_table = 'id_' . preg_replace('/^' . _DB_PREFIX_ . '/', '', $table);

            if (in_array($table_shop, [_DB_PREFIX_ . 'carrier_tax_rules_group_shop'])) {
                continue;
            }

            $queryDeleteShops = $this->db->delete('`' . bqSQL($table_shop) . '`', '`' . bqSQL($id_table) . '` NOT IN (SELECT `' . bqSQL($id_table) . '` FROM `' . bqSQL($table) . '`)');
            if ($queryDeleteShops) {
                if ($affected_rows = $this->db->Affected_Rows()) {
                    $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . bqSQL($table_shop));
                    $this->output[$this->module->l(sprintf('Table %s, %s not in %s', $table_shop, $id_table, $table), 'miscCleaner')] = $affected_rows;
                }
            }

            $queryDeleteShops = $this->db->delete('`' . bqSQL($table_shop) . '`', '`id_shop` NOT IN (SELECT `id_shop` FROM `' . _DB_PREFIX_ . 'shop`)');
            if ($queryDeleteShops) {
                if ($affected_rows = $this->db->Affected_Rows()) {
                    $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . bqSQL($table_shop));
                    $this->output[$this->module->l(sprintf('Table %s, id shop not in %s', $table_shop, $table), 'miscCleaner')] = $affected_rows;
                }
            }
        }

        // Orphans stocks
        $deleteStockAvailables = $this->db->delete('stock_available', '`id_shop` NOT IN (SELECT `id_shop` FROM `' . _DB_PREFIX_ . 'shop`) AND `id_shop_group` NOT IN (SELECT `id_shop_group` FROM `' . _DB_PREFIX_ . 'shop_group`)');
        if ($deleteStockAvailables) {
            if ($affected_rows = $this->db->Affected_Rows()) {
                $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . 'stock_available');
                $this->output[$this->module->l('Stocks orphans', 'miscCleaner')] = $affected_rows;
            }
        }

        // Orphans address
        $deleteAddressOrphans = $this->db->delete('address', 'id_customer NOT IN (SELECT id_customer FROM `' . _DB_PREFIX_ . 'customer`)');
        if ($deleteAddressOrphans) {
            if ($affected_rows = $this->db->Affected_Rows()) {
                $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . 'address');
                $this->output[$this->module->l('Address orphans', 'miscCleaner')] = $affected_rows;
            }
        }

        Category::regenerateEntireNtree();
    }

    /**
     * Return tables with id & associatives tables
     *
     * @return array $tables
     */
    private function getCheckAndFixQueries()
    {
        $tables = [
            ['access', 'id_profile', 'profile', 'id_profile'],
            ['accessory', 'id_product_1', 'product', 'id_product'],
            ['accessory', 'id_product_2', 'product', 'id_product'],
            ['address_format', 'id_country', 'country', 'id_country'],
            ['attribute', 'id_attribute_group', 'attribute_group', 'id_attribute_group'],
            ['carrier_group', 'id_carrier', 'carrier', 'id_carrier'],
            ['carrier_group', 'id_group', 'group', 'id_group'],
            ['carrier_zone', 'id_carrier', 'carrier', 'id_carrier'],
            ['carrier_zone', 'id_zone', 'zone', 'id_zone'],
            ['cart_cart_rule', 'id_cart', 'cart', 'id_cart'],
            ['cart_product', 'id_cart', 'cart', 'id_cart'],
            ['cart_rule_carrier', 'id_cart_rule', 'cart_rule', 'id_cart_rule'],
            ['cart_rule_carrier', 'id_carrier', 'carrier', 'id_carrier'],
            ['cart_rule_combination', 'id_cart_rule_1', 'cart_rule', 'id_cart_rule'],
            ['cart_rule_combination', 'id_cart_rule_2', 'cart_rule', 'id_cart_rule'],
            ['cart_rule_country', 'id_cart_rule', 'cart_rule', 'id_cart_rule'],
            ['cart_rule_country', 'id_country', 'country', 'id_country'],
            ['cart_rule_group', 'id_cart_rule', 'cart_rule', 'id_cart_rule'],
            ['cart_rule_group', 'id_group', 'group', 'id_group'],
            ['cart_rule_lang', 'id_cart_rule', 'cart_rule', 'id_cart_rule'],
            ['cart_rule_lang', 'id_lang', 'lang', 'id_lang'],
            ['cart_rule_product_rule_group', 'id_cart_rule', 'cart_rule', 'id_cart_rule'],
            ['cart_rule_product_rule', 'id_product_rule_group', 'cart_rule_product_rule_group', 'id_product_rule_group'],
            ['cart_rule_product_rule_value', 'id_product_rule', 'cart_rule_product_rule', 'id_product_rule'],
            ['category_group', 'id_category', 'category', 'id_category'],
            ['category_group', 'id_group', 'group', 'id_group'],
            ['category_product', 'id_category', 'category', 'id_category'],
            ['category_product', 'id_product', 'product', 'id_product'],
            ['cms', 'id_cms_category', 'cms_category', 'id_cms_category'],
            ['cms_block', 'id_cms_category', 'cms_category', 'id_cms_category', 'blockcms'],
            ['cms_block_page', 'id_cms', 'cms', 'id_cms', 'blockcms'],
            ['cms_block_page', 'id_cms_block', 'cms_block', 'id_cms_block', 'blockcms'],
            ['connections', 'id_shop_group', 'shop_group', 'id_shop_group'],
            ['connections', 'id_shop', 'shop', 'id_shop'],
            ['connections_page', 'id_connections', 'connections', 'id_connections'],
            ['connections_page', 'id_page', 'page', 'id_page'],
            ['connections_source', 'id_connections', 'connections', 'id_connections'],
            ['customer', 'id_shop_group', 'shop_group', 'id_shop_group'],
            ['customer', 'id_shop', 'shop', 'id_shop'],
            ['customer_group', 'id_group', 'group', 'id_group'],
            ['customer_group', 'id_customer', 'customer', 'id_customer'],
            ['customer_message', 'id_customer_thread', 'customer_thread', 'id_customer_thread'],
            ['customer_thread', 'id_shop', 'shop', 'id_shop'],
            ['customization', 'id_cart', 'cart', 'id_cart'],
            ['customization_field', 'id_product', 'product', 'id_product'],
            ['customized_data', 'id_customization', 'customization', 'id_customization'],
            ['delivery', 'id_shop', 'shop', 'id_shop'],
            ['delivery', 'id_shop_group', 'shop_group', 'id_shop_group'],
            ['delivery', 'id_carrier', 'carrier', 'id_carrier'],
            ['delivery', 'id_zone', 'zone', 'id_zone'],
            ['editorial', 'id_shop', 'shop', 'id_shop', 'editorial'],
            ['favorite_product', 'id_product', 'product', 'id_product', 'favoriteproducts'],
            ['favorite_product', 'id_customer', 'customer', 'id_customer', 'favoriteproducts'],
            ['favorite_product', 'id_shop', 'shop', 'id_shop', 'favoriteproducts'],
            ['feature_product', 'id_feature', 'feature', 'id_feature'],
            ['feature_product', 'id_product', 'product', 'id_product'],
            ['feature_value', 'id_feature', 'feature', 'id_feature'],
            ['group_reduction', 'id_group', 'group', 'id_group'],
            ['group_reduction', 'id_category', 'category', 'id_category'],
            ['homeslider', 'id_shop', 'shop', 'id_shop', 'homeslider'],
            ['homeslider', 'id_homeslider_slides', 'homeslider_slides', 'id_homeslider_slides', 'homeslider'],
            ['hook_module', 'id_hook', 'hook', 'id_hook'],
            ['hook_module', 'id_module', 'module', 'id_module'],
            ['hook_module_exceptions', 'id_hook', 'hook', 'id_hook'],
            ['hook_module_exceptions', 'id_module', 'module', 'id_module'],
            ['hook_module_exceptions', 'id_shop', 'shop', 'id_shop'],
            ['image', 'id_product', 'product', 'id_product'],
            ['message', 'id_cart', 'cart', 'id_cart'],
            ['message_readed', 'id_message', 'message', 'id_message'],
            ['message_readed', 'id_employee', 'employee', 'id_employee'],
            ['module_access', 'id_profile', 'profile', 'id_profile'],
            ['module_country', 'id_module', 'module', 'id_module'],
            ['module_country', 'id_country', 'country', 'id_country'],
            ['module_country', 'id_shop', 'shop', 'id_shop'],
            ['module_currency', 'id_module', 'module', 'id_module'],
            ['module_currency', 'id_currency', 'currency', 'id_currency'],
            ['module_currency', 'id_shop', 'shop', 'id_shop'],
            ['module_group', 'id_module', 'module', 'id_module'],
            ['module_group', 'id_group', 'group', 'id_group'],
            ['module_group', 'id_shop', 'shop', 'id_shop'],
            ['module_preference', 'id_employee', 'employee', 'id_employee'],
            ['orders', 'id_shop', 'shop', 'id_shop'],
            ['orders', 'id_shop_group', 'group_shop', 'id_shop_group'],
            ['order_carrier', 'id_order', 'orders', 'id_order'],
            ['order_cart_rule', 'id_order', 'orders', 'id_order'],
            ['order_detail', 'id_order', 'orders', 'id_order'],
            ['order_detail_tax', 'id_order_detail', 'order_detail', 'id_order_detail'],
            ['order_history', 'id_order', 'orders', 'id_order'],
            ['order_invoice', 'id_order', 'orders', 'id_order'],
            ['order_invoice_payment', 'id_order', 'orders', 'id_order'],
            ['order_invoice_tax', 'id_order_invoice', 'order_invoice', 'id_order_invoice'],
            ['order_return', 'id_order', 'orders', 'id_order'],
            ['order_return_detail', 'id_order_return', 'order_return', 'id_order_return'],
            ['order_slip', 'id_order', 'orders', 'id_order'],
            ['order_slip_detail', 'id_order_slip', 'order_slip', 'id_order_slip'],
            ['pack', 'id_product_pack', 'product', 'id_product'],
            ['pack', 'id_product_item', 'product', 'id_product'],
            ['page', 'id_page_type', 'page_type', 'id_page_type'],
            ['page_viewed', 'id_shop', 'shop', 'id_shop'],
            ['page_viewed', 'id_shop_group', 'shop_group', 'id_shop_group'],
            ['page_viewed', 'id_date_range', 'date_range', 'id_date_range'],
            ['product_attachment', 'id_attachment', 'attachment', 'id_attachment'],
            ['product_attachment', 'id_product', 'product', 'id_product'],
            ['product_attribute', 'id_product', 'product', 'id_product'],
            ['product_attribute_combination', 'id_product_attribute', 'product_attribute', 'id_product_attribute'],
            ['product_attribute_combination', 'id_attribute', 'attribute', 'id_attribute'],
            ['product_attribute_image', 'id_image', 'image', 'id_image'],
            ['product_attribute_image', 'id_product_attribute', 'product_attribute', 'id_product_attribute'],
            ['product_carrier', 'id_product', 'product', 'id_product'],
            ['product_carrier', 'id_shop', 'shop', 'id_shop'],
            ['product_carrier', 'id_carrier_reference', 'carrier', 'id_reference'],
            ['product_country_tax', 'id_product', 'product', 'id_product'],
            ['product_country_tax', 'id_country', 'country', 'id_country'],
            ['product_country_tax', 'id_tax', 'tax', 'id_tax'],
            ['product_download', 'id_product', 'product', 'id_product'],
            ['product_group_reduction_cache', 'id_product', 'product', 'id_product'],
            ['product_group_reduction_cache', 'id_group', 'group', 'id_group'],
            ['product_sale', 'id_product', 'product', 'id_product'],
            ['product_supplier', 'id_product', 'product', 'id_product'],
            ['product_supplier', 'id_supplier', 'supplier', 'id_supplier'],
            ['product_tag', 'id_product', 'product', 'id_product'],
            ['product_tag', 'id_tag', 'tag', 'id_tag'],
            ['range_price', 'id_carrier', 'carrier', 'id_carrier'],
            ['range_weight', 'id_carrier', 'carrier', 'id_carrier'],
            ['referrer_cache', 'id_referrer', 'referrer', 'id_referrer'],
            ['referrer_cache', 'id_connections_source', 'connections_source', 'id_connections_source'],
            ['search_index', 'id_product', 'product', 'id_product'],
            ['search_word', 'id_lang', 'lang', 'id_lang'],
            ['search_word', 'id_shop', 'shop', 'id_shop'],
            ['shop_url', 'id_shop', 'shop', 'id_shop'],
            ['specific_price_priority', 'id_product', 'product', 'id_product'],
            ['stock', 'id_warehouse', 'warehouse', 'id_warehouse'],
            ['stock', 'id_product', 'product', 'id_product'],
            ['stock_available', 'id_product', 'product', 'id_product'],
            ['stock_mvt', 'id_stock', 'stock', 'id_stock'],
            ['tab_module_preference', 'id_employee', 'employee', 'id_employee'],
            ['tab_module_preference', 'id_tab', 'tab', 'id_tab'],
            ['tax_rule', 'id_country', 'country', 'id_country'],
            ['warehouse_carrier', 'id_warehouse', 'warehouse', 'id_warehouse'],
            ['warehouse_carrier', 'id_carrier', 'carrier', 'id_carrier'],
            ['warehouse_product_location', 'id_product', 'product', 'id_product'],
            ['warehouse_product_location', 'id_warehouse', 'warehouse', 'id_warehouse'],
        ];

        return $this->bulle($tables);
    }

    /**
     * Sort array tables
     *
     * @param $array
     * @return array
     */
    private function bulle($array)
    {
        $sorted = false;
        $size = count($array);
        while (!$sorted) {
            $sorted = true;
            for ($i = 0; $i < $size - 1; ++$i) {
                for ($j = $i + 1; $j < $size; ++$j) {
                    if ($array[$i][2] == $array[$j][0]) {
                        $tmp = $array[$i];
                        $array[$i] = $array[$j];
                        $array[$j] = $tmp;
                        $sorted = false;
                    }
                }
            }
        }

        return $array;
    }

    /***********************************************************************************************************************************************
    * Stats & Logs
    ***********************************************************************************************************************************************/

    /**
     * Clean logs & images temporary files on server
     */
    public function cleanStatsAndLogs()
    {
        $tables = $this->getStatsAndLogsTables();

        foreach ($tables as $table) {
            $rows_count = $this->db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . bqSQL($table) . '`');
            $queryTruncate = $this->db->execute('TRUNCATE TABLE `' . _DB_PREFIX_ . bqSQL($table) . '`');
            if ($queryTruncate && $rows_count > 0) {
                $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . bqSQL($table));
                $this->output[$this->module->l(sprintf('Table %s cleaned', $table), 'miscCleaner')] = $rows_count;
            }
        }
    }

    /**
     * Return tables of stats & logs
     *
     * @return array tables
     */
    private function getStatsAndLogsTables()
    {
        return [
            'customer_session',
            'employee_session',
            'connections',
            'connections_page',
            'connections_source',
            'guest',
            'pagenotfound',
            'page_viewed',
            'referrer_cache',
        ];
    }

    /**
     * Clean server files logs & temporary images + cache Smarty
     */
    private function cleanLogsImgFiles()
    {
        $path = _PS_CORE_DIR_ . '/var/logs/*.log';
        $nb_files = count(glob($path));
        array_map('unlink', glob("$path"));
        if ($nb_files > 1) {
            $this->output[$this->module->l('Logs files', 'miscCleaner')] = $nb_files;
        }

        $this->clearAllCachesAndTmp();
    }

    /**
     * Clear Smarty cache & images in tmp dir
     */
    private function clearAllCachesAndTmp()
    {
        // @Todo: Remove attachment files, images...

        if (is_dir(_PS_TMP_IMG_DIR_)) {
            $index = file_exists(_PS_TMP_IMG_DIR_ . 'index.php') ? Tools::file_get_contents(_PS_TMP_IMG_DIR_ . 'index.php') : '';
            $nb_files = count(glob(_PS_TMP_IMG_DIR_));
            Tools::deleteDirectory(_PS_TMP_IMG_DIR_, false);
            file_put_contents(_PS_TMP_IMG_DIR_ . 'index.php', $index);
        }
        
        Context::getContext()->smarty->clearAllCache();

        if ($nb_files > 1) {
            $this->output[$this->module->l('Images temporary files', 'miscCleaner')] = $nb_files;
        }
    }
}
