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
class ProductCleaner
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
    public $type;
    public $categories;
    public $active;

    public function __construct()
    {
        $this->context = Context::getContext();
        $this->db = Db::getInstance();
        $this->lang = $this->context->language;
        $this->shop = $this->context->shop;
        $this->module = Module::getInstanceByName('prestaclean');
    }

    /***********************************************************************************************************************************************
    * Delete
    ***********************************************************************************************************************************************/

    /**
     * Delete products by ids given or by conditions
     *
     * @param $id_products
     */
    public function deleteProducts($id_products = null)
    {
        if ($id_products === null || !is_array($id_products)) {
            $sql = new DbQuery();
            $sql->select('p.id_product');
            $sql->from('product', 'p');
            $sql->innerJoin('product_shop', 'ps', 'ps.id_product=p.id_product');
            if ($this->date_from && $this->date_to) {
                $date_from = DateTime::createFromFormat('!d/m/Y H:i', $this->date_from)->getTimestamp();
                $date_to = DateTime::createFromFormat('!d/m/Y H:i', $this->date_to)->getTimestamp();

                $sql->where('UNIX_TIMESTAMP(p.date_add) BETWEEN ' . pSQL($date_from) . ' AND ' . pSQL($date_to));
            }
            if ($this->shops) {
                $sql->where('ps.id_shop IN (' . pSQL($this->shops) . ')');
            }
            if ($this->types) {
                $sql->where('p.product_type IN (' . pSQL($this->types) . ')');
            }
            if ($this->categories) {
                $sql->where('p.id_category_default IN (' . pSQL($this->categories) . ')');
            }
            if ($this->active) {
                $sql->where('p.active = 1');
            }
            $sql->orderBy('p.id_product');

            $id_products = $this->db->executeS($sql) ?? [];
            $id_products = array_column($id_products, 'id_product');
        }

        if (empty($id_products)) {
            $this->context->controller->confirmations[] = $this->module->l('Nothing to delete', 'productCleaner');

            return;
        }

        $sqlCount = 'SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'product';

        $productsBefore = Db::getInstance()->getValue($sqlCount);
        $productsDelete = $this->processDelete($id_products);
        $productsAfter = Db::getInstance()->getValue($sqlCount);
        $nbDeleted = $productsBefore - $productsAfter;

        if ($productsDelete) {
            $logs = '';
            $this->context->controller->confirmations[] = $this->module->l('Success!', 'productCleaner');
            $this->context->controller->confirmations[] = sprintf($this->module->l('%s product(s) deleted.', 'productCleaner'), $nbDeleted);

            return;
        }

        $this->context->controller->errors[] = $this->module->l('An error occured will processing', 'productCleaner');
    }

    /**
     * Process delete products & related tables data's
     *
     * @param $id_products
     * @return bool $res
     */
    public function processDelete($id_products)
    {
        $res = true;

        foreach ($id_products as $id_product) {
            if (Validate::isLoadedObject($product = new Product((int) $id_product))) {
                $res &= $product->delete();
            }
        }
        // TODO: add another related table to refresh size
        $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . 'product');

        return $res;
    }

    /***********************************************************************************************************************************************
    * Create Dummies
    ***********************************************************************************************************************************************/

    /**
     * Create dummy products
     * @param $nb
     */
    public function createDummyProducts($nb = 50)
    {
        for ($i = 1; $i <= $nb; ++$i) {
            $rand_str = substr(str_shuffle(str_repeat('abcdefghijklmnopqrstuvwxyz', 5)), 0, 5);
            $name = sprintf('Product generated %d', rand(100, 99999));

            $product = new Product();
            $product->name = $this->createMultiLangField($name);
            $product->description_short = $this->randomStr(50);
            $product->description = $this->randomStr(200);
            $product->width = rand(1, 50);
            $product->height = rand(1, 50);
            $product->depth = rand(1, 50);
            $product->weight = rand(1, 50);
            $product->link_rewrite = $this->createMultiLangField(Tools::str2url($name));
            $product->quantity = rand(1, 50);
            $product->price = rand(1, 50);
            $product->active = 1;
            $product->save();

            StockAvailable::setQuantity($product->id, null, $product->quantity);

            $categories = array_column(Category::getSimpleCategoriesWithParentInfos($this->lang->id), 'id_category');
            $default_category = $categories[array_rand($categories)];
            $categories = [Configuration::get('PS_HOME_CATEGORY'), $default_category];
            $product->addToCategories($categories);
            $product->id_category_default = $default_category;
            $product->update();
        }

        Context::getContext()->controller->confirmations[] = sprintf('%d product(s) successfully created.', $nb);
    }

    /***********************************************************************************************************************************************
    * Misc
    ***********************************************************************************************************************************************/

    /**
     * Random string
     * @param $length
     */
    private function randomStr($length)
    {
        return $rand_str = substr(str_shuffle(str_repeat('abcdefghijklmnopqrstuvwxyz', $length)), 0, $length);
    }

    /**
     * Set same text to all existing language
     * @param $field
     */
    private function createMultiLangField($field)
    {
        $res = [];
        foreach (Language::getIDs(false) as $id_lang) {
            $res[$id_lang] = $field;
        }

        return $res;
    }
}
