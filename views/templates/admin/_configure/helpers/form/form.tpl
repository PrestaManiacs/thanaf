{*
* 2006-2021 THECON SRL
*
* NOTICE OF LICENSE
*
* DISCLAIMER
*
* YOU ARE NOT ALLOWED TO REDISTRIBUTE OR RESELL THIS FILE OR ANY OTHER FILE
* USED BY THIS MODULE.
*
* @author    THECON SRL <contact@thecon.ro>
* @copyright 2006-2021 THECON SRL
* @license   Commercial
*}

{extends file="helpers/form/form.tpl"}
{block name="input_row"}
    {if $input.type == 'html_title'}
        <div class="form-group">
            <div class="col-xs-12 col-lg-10 col-lg-offset-1">
                {if isset($input.html_content)}
                    {$input.html_content nofilter}
                {else}
                    <div class="custom-html-title">{$input.name|escape:'htmlall':'UTF-8'}</div>
                {/if}
            </div>
        </div>
    {elseif $input.type == 'html_button'}
        <div class="form-group">
            <label class="control-label col-lg-3 text-right">
                <span class="thpriceh-result"></span>
            </label>
            <div class="col-lg-4">
                <div class="custom-html-button">
                    <button class="btn btn-info {if isset($input.class) && $input.class}{$input.class|escape:'htmlall':'UTF-8'}{/if}" {if isset($input.c_attr_name) && $input.c_attr_name}{$input.c_attr_name|escape:'htmlall':'UTF-8'}="{if isset($input.c_attr_value) && $input.c_attr_value}{$input.c_attr_value|escape:'htmlall':'UTF-8'}{/if}"{/if}>
                        <span>{$input.name|escape:'htmlall':'UTF-8'}</span>
                    </button>
                </div>
            </div>
        </div>
    {else}
        {$smarty.block.parent}
    {/if}
{/block}
