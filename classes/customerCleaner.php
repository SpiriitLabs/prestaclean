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
class CustomerCleaner
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
    public $guest;
    public $never_ordered;

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
     * Delete customers by ids given or by conditions
     *
     * @param $id_customers
     */
    public function deleteCustomers($id_customers = null)
    {
        if ($id_customers === null || !is_array($id_customers)) {
            $sql = new DbQuery();
            $sql->select('id_customer');
            $sql->from('customer', 'c');
            if ($this->date_from && $this->date_to) {
                $date_from = DateTime::createFromFormat('!d/m/Y H:i', $this->date_from)->getTimestamp();
                $date_to = DateTime::createFromFormat('!d/m/Y H:i', $this->date_to)->getTimestamp();

                $sql->where('UNIX_TIMESTAMP(c.date_add) BETWEEN ' . pSQL($date_from) . ' AND ' . pSQL($date_to));
            }
            if ($this->shops) {
                $sql->where('c.id_shop IN (' . pSQL($this->shops) . ')');
            }
            if ($this->guest) {
                $sql->where('c.is_guest = 1');
            }
            if ($this->never_ordered) {
                $sql->where('c.id_customer NOT IN (SELECT id_customer FROM ' . _DB_PREFIX_ . 'orders)');
            }
            $sql->orderBy('c.id_customer');

            $id_customers = $this->db->executeS($sql) ?? [];
            $id_customers = array_column($id_customers, 'id_customer');
        }

        if (empty($id_customers)) {
            $this->context->controller->confirmations[] = $this->module->l('Nothing to delete', 'customerCleaner');

            return;
        }

        $customersBefore = count(Customer::getCustomers());
        $customersDelete = $this->processDelete($id_customers);
        $customersAfter = count(Customer::getCustomers());
        $nbDeleted = $customersBefore - $customersAfter;

        if ($customersDelete) {
            $logs = '';
            $this->context->controller->confirmations[] = $this->module->l('Success!', 'customerCleaner');

            $this->context->controller->confirmations[] = sprintf($this->module->l('%s customer(s) deleted.'), $nbDeleted);

            return;
        }

        $this->context->controller->errors[] = $this->module->l('An error occured will processing', 'customerCleaner');
    }

    /**
     * Process delete customers
     *
     * @param $id_customers
     * @return bool $res
     */
    public function processDelete($id_customers)
    {
        $res = true;

        foreach ($id_customers as $id_customer) {
            if (Validate::isLoadedObject($customer = new Customer((int) $id_customer))) {
                $res &= $customer->delete();
            }
        }

        // TODO: add another related table to refresh size
        $this->db->execute('ANALYZE TABLE ' . _DB_PREFIX_ . 'customer');

        return $res;
    }

    /***********************************************************************************************************************************************
    * Create Dummies
    ***********************************************************************************************************************************************/

    /**
     * Create dummy customers
     * @param $nb
     */
    public function createDummyCustomers($nb = 50)
    {
        $countries = Country::getCountries($this->lang->id, true);
        $id_country = $countries[array_rand($countries)]['id_country'] ?? 1;

        for ($o = 1; $o <= $nb; ++$o) {
            $rand_str = substr(str_shuffle(str_repeat('abcdefghijklmnopqrstuvwxyz', 5)), 0, 5);

            $customer = new Customer();
            $customer->email = rand(999, 9999999) . '@random.com';
            $customer->passwd = md5(pSQL(_COOKIE_KEY_ . Tools::passwdGen()));
            $customer->date_add = date('Y-m-d H:i:s');
            $customer->id_gender = rand(1, 3);
            $customer->lastname = 'Lastname' . $rand_str;
            $customer->firstname = 'Firstname' . $rand_str;
            $customer->save();

            $address = new Address();
            $address->id_customer = $customer->id;
            $address->alias = 'Default';
            $address->lastname = $customer->lastname;
            $address->firstname = $customer->firstname;
            $address->address1 = 'Address generated';
            $address->address2 = '';
            $address->postcode = rand(10000, 95000);
            $address->city = 'Default';
            $address->id_country = $id_country;
            $address->phone = '0000000000';
            $address->phone_mobile = '0000000000';
            $address->save();
        }

        Context::getContext()->controller->confirmations[] = $this->module->l(sprintf('%d customer(s) successfully created.', $nb), 'customerCleaner');
    }
}
