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
class PrestaCleanHelper
{
    private $lang;
    private $shop;
    private $db;
    public $context;
    public $module;

    public function __construct(Module $module, Db $db = null, Language $lang = null, Shop $shop = null)
    {
        $this->context = Context::getContext();
        $this->db = $db ?? Db::getInstance();
        $this->lang = $lang ?? $this->context->language;
        $this->shop = $shop ?? $this->context->shop;
        $this->module = $module;
    }

    /***********************************************************************************************************************************************
    * Return vars to assign to Smarty
    ***********************************************************************************************************************************************/

    /**
     * Return array of general vars for Smarty
     *
     * @return array
     */
    public function getGeneralVars()
    {
        return [
            'dbSize' => $this->getSizeDB(),
            'logFiles' => $this->logsFilesInfos(),
            'imgFiles' => $this->imgTmpInfos(),
        ];
    }

    /**
     * Return array of controllers for Smarty
     *
     * @return array
     */
    public function getControllersVars()
    {
        return [
            'miscController' => $this->context->link->getAdminLink('AdminCleanMisc'),
            'ordersController' => $this->context->link->getAdminLink('AdminCleanOrder'),
            'cartsController' => $this->context->link->getAdminLink('AdminCleanCart'),
            'productsController' => $this->context->link->getAdminLink('AdminCleanProduct'),
            'customersController' => $this->context->link->getAdminLink('AdminCleanCustomer'),
            'resetController' => $this->context->link->getAdminLink('AdminCleanReset'),
        ];
    }

    /***********************************************************************************************************************************************
    * Get size of Database
    ***********************************************************************************************************************************************/

    /**
     * Return readable size of database
     *
     * @return int
     */
    private function getSizeDB()
    {
        $sql = 'SELECT ROUND(SUM(data_length + index_length), 1)
        FROM information_schema.tables
        WHERE table_schema="' . _DB_NAME_ . '"
        GROUP BY table_schema;';
        $size = Db::getInstance()->getValue($sql);

        return $this->readableBytes($size);
    }

    /***********************************************************************************************************************************************
    * Get files infos for images, logs ...
    ***********************************************************************************************************************************************/

    /**
     * Return array of with informations about logs files
     *
     * @return array
     */
    private function logsFilesInfos()
    {
        $path = _PS_CORE_DIR_ . '/var/logs/*.log';
        $nb_files = count(glob($path, GLOB_BRACE));
        $files = glob($path);
        $size = 0;

        foreach ($files as $path) {
            is_file($path) && $size += filesize($path);
            is_dir($path) && $size += get_dir_size($path);
        }

        return ['size' => $this->readableBytes($size), 'num' => $nb_files];
    }

    /**
     * Return array of informations about images temp files
     *
     * @return array
     */
    private function imgTmpInfos()
    {
        $path = _PS_TMP_IMG_DIR_ . '/[!{index}]*';
        $nb_files = count(glob($path, GLOB_BRACE));
        $files = glob($path, GLOB_BRACE);
        $size = 0;

        foreach ($files as $path) {
            is_file($path) && $size += filesize($path);
        }

        return ['size' => $this->readableBytes($size), 'num' => $nb_files];
    }

    /***********************************************************************************************************************************************
    * Usefull functions
    ***********************************************************************************************************************************************/

    /**
     * Return readable string of size from bytes
     *
     * @return string
     */
    private function readableBytes($bytes)
    {
        $format = [
            $this->module->l('B', 'prestaCleanHelper'),
            $this->module->l('KB', 'prestaCleanHelper'),
            $this->module->l('MB', 'prestaCleanHelper'),
            $this->module->l('GB', 'prestaCleanHelper'),
            $this->module->l('TB', 'prestaCleanHelper'),
            $this->module->l('PB', 'prestaCleanHelper'),
        ];

        if ($bytes == 0) {
            return sprintf('0.00 %s', $format[0]);
        }

        $e = floor(log($bytes, 1024));

        return sprintf('%s %s', round($bytes / pow(1024, $e), 2), $format[$e]);
    }
}
