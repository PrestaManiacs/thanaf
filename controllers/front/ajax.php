<?php
/**
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
 */

class ThanafAjaxModuleFrontController extends ModuleFrontController
{
    public $ajax = true;

    public function init()
    {
        if (!$this->isTokenValid()
            || !Module::isInstalled($this->module->name)
        ) {
            die('Bad token');
        }

        parent::init();

        if (Tools::getValue('action') == 'getCompanyDetails') {
            $cui = trim(Tools::getValue('vat_number'));
            $response = $this->module->curl($cui);
            $this->ajaxDie(Tools::jsonEncode($response));
            exit;
        }

        exit;
    }
}
