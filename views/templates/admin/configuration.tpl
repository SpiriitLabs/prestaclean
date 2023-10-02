{*
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
*}

<div class="row">
    <div class="col-md-12">
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-heartbeat"></i>{l s='Health center' mod='prestaclean'}
            </div>
            <div class="row">
                <div class="col-md-8">
                    <p class="h4">{l s='Keep your shop clean & delete all garbages data in your database and your server.' mod='prestaclean'}</p>
                    <div class="alert alert-danger">{l s='Be careful when you use this module, some features can broke your Shop, please backup your database before doing anything.' mod='prestaclean'}</div>
                </div>
                <div class="col-md-4">
                    <ul class="list-group">
                        <li class="list-group-item list-group-item-action">{l s='DB Size' mod='prestaclean'} <span class="badge badge-success rounded">{$dbSize}</span></li>
                        <li class="list-group-item list-group-item-action">{l s='Logs Files' mod='prestaclean'} <span class="badge badge-success rounded">{$logFiles.num} / {$logFiles.size}</span></li>
                        <li class="list-group-item list-group-item-action">{l s='Images temporary' mod='prestaclean'} <span class="badge badge-success rounded">{$imgFiles.num} / {$imgFiles.size}</span></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-2">
        <div class="list-group">
            <a href="{$miscController}" class="list-group-item list-group-item-action {if $controller == 'CleanMisc'}active{/if}">{l s='Clean & Optimize' mod='prestaclean'}</a>
            {if _PS_MODE_DEV_}
            <a href="{$ordersController}" class="list-group-item list-group-item-action {if $controller == 'CleanOrder'}active{/if}">{l s='Orders' mod='prestaclean'}</a>
            {/if}
            <a href="{$cartsController}" class="list-group-item list-group-item-action {if $controller == 'CleanCart'}active{/if}">{l s='Carts' mod='prestaclean'}</a>
            <a href="{$productsController}" class="list-group-item list-group-item-action {if $controller == 'CleanProduct'}active{/if}">{l s='Products' mod='prestaclean'}</a>
            <a href="{$customersController}" class="list-group-item list-group-item-action {if $controller == 'CleanCustomer'}active{/if}">{l s='Customers' mod='prestaclean'}</a>
            {if _PS_MODE_DEV_}
            <a href="{$resetController}" class="list-group-item list-group-item-action {if $controller == 'CleanReset'}active{/if}">{l s='Reset all' mod='prestaclean'}</a>
            {/if}
        </div>
    </div>
    <div class="col-md-10">
        {$form}
    </div>
</div>
