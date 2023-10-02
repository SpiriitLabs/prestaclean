/*
* 2013-2023 In Spiriit
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
document.addEventListener("DOMContentLoaded", function() {
    const deleteBtn = document.querySelectorAll('.delete_carts_btn');

    $('.date_time').datetimepicker();

    new TomSelect('#select-shops', {
        persist: false,
        sortField: 'text',
    });

    // Confirm delete button click
    deleteBtn && deleteBtn.forEach(btn => btn.addEventListener('click', function(e) {
        if(!confirm(confirmDeleteLang)) {
            e.preventDefault();
            e.stopPropagation();
            return;
        }
    }));
});
