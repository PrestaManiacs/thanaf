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

if (!defined('_PS_VERSION_')) {
    exit;
}

class Thanaf extends Module
{
    protected $config_form = false;

    const TH_ANAF_API_URL = 'https://webservicesp.anaf.ro/PlatitorTvaRest/api/v6/ws/tva';

    public function __construct()
    {
        $this->name = 'thanaf';
        $this->tab = 'front_office_features';
        $this->version = '1.0.3';
        $this->author = 'Presta Maniacs';
        $this->need_instance = 0;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Autocomplete Company Details from ANAF API');
        $this->description = $this->l('Automatically fills in the Customer Company Details with the data received from ANAF API, according to the completed Vat Number');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        if (extension_loaded('curl') == false) {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
            return false;
        }

        Configuration::updateValue('THANAF_LIVE_MODE', false);
        Configuration::updateValue('THANAF_VAT_NUMBER', 'input[name="vat_number"]');
        Configuration::updateValue('THANAF_COMPANY', 'input[name="company"]');
        Configuration::updateValue('THANAF_REG', 'input[name="dni"]');
        Configuration::updateValue('THANAF_POSTCODE', 'input[name="postcode"]');
        Configuration::updateValue('THANAF_PHONE', 'input[name="phone"]');
        Configuration::updateValue('THANAF_STATE', 'select[name="id_state"]');
        Configuration::updateValue('THANAF_CITY', 'input[name="city"]');
        Configuration::updateValue('THANAF_ADDRESS', 'input[name="address1"]');

        if (!parent::install()) {
            return false;
        }

        if (!$this->registerHooks()) {
            return false;
        }

        return true;
    }

    public function registerHooks()
    {
        if (!$this->registerHook('backOfficeHeader') ||
            !$this->registerHook('actionFrontControllerSetMedia')
        ) {
            return false;
        }

        if ($this->getPsVersion() == '6') {
            if (!$this->registerHook('header')) {
                return false;
            }
        }

        return true;
    }

    public function uninstall()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::deleteByName($key);
        }

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        $message = '';
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitThanafModule')) == true) {
            $this->postProcess();

            if (count($this->_errors)) {
                $message = $this->displayError($this->_errors);
            } else {
                $message = $this->displayConfirmation($this->l('Successfully saved!'));
            }
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $message.$output.$this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitThanafModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Live mode'),
                        'name' => 'THANAF_LIVE_MODE',
                        'is_bool' => true,
                        'desc' => $this->l('Use this module in live mode'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'col' => 4,
                        'type' => 'text',
                        'name' => 'THANAF_VAT_NUMBER',
                        'label' => $this->l('Vat Number Selector:'),
                    ),
                    array(
                        'col' => 4,
                        'type' => 'text',
                        'name' => 'THANAF_COMPANY',
                        'label' => $this->l('Company Selector:'),
                    ),
                    array(
                        'type' => 'html_title',
                        'label' => '',
                        'name' => $this->l('DNI Autocomplete'),
                        'col' => 4
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Enable autocomplete for DNI:'),
                        'name' => 'THANAF_REG_AUTO',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'col' => 4,
                        'type' => 'text',
                        'name' => 'THANAF_REG',
                        'label' => $this->l('DNI Selector:'),
                    ),
                    array(
                        'type' => 'html_title',
                        'label' => '',
                        'name' => $this->l('Postcode Autocomplete'),
                        'col' => 4
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Enable autocomplete for Postcode:'),
                        'name' => 'THANAF_POSTCODE_AUTO',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'col' => 4,
                        'type' => 'text',
                        'name' => 'THANAF_POSTCODE',
                        'label' => $this->l('Postcode Selector:'),
                    ),
                    array(
                        'type' => 'html_title',
                        'label' => '',
                        'name' => $this->l('Phone Autocomplete'),
                        'col' => 4
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Enable autocomplete for Phone:'),
                        'name' => 'THANAF_PHONE_AUTO',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'col' => 4,
                        'type' => 'text',
                        'name' => 'THANAF_PHONE',
                        'label' => $this->l('Phone Selector:'),
                    ),
                    array(
                        'type' => 'html_title',
                        'label' => '',
                        'name' => $this->l('State Autocomplete'),
                        'col' => 4
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Enable autocomplete for State:'),
                        'name' => 'THANAF_STATE_AUTO',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'col' => 4,
                        'type' => 'text',
                        'name' => 'THANAF_STATE',
                        'label' => $this->l('State Selector:'),
                    ),
                    array(
                        'type' => 'html_title',
                        'label' => '',
                        'name' => $this->l('City Autocomplete'),
                        'col' => 4
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Enable autocomplete for City:'),
                        'name' => 'THANAF_CITY_AUTO',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'col' => 4,
                        'type' => 'text',
                        'name' => 'THANAF_CITY',
                        'label' => $this->l('City Selector:'),
                    ),
                    array(
                        'type' => 'html_title',
                        'label' => '',
                        'name' => $this->l('Address Autocomplete'),
                        'col' => 4
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Enable autocomplete for Address:'),
                        'name' => 'THANAF_ADDRESS_AUTO',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'col' => 4,
                        'type' => 'text',
                        'name' => 'THANAF_ADDRESS',
                        'label' => $this->l('Address Selector:'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'THANAF_LIVE_MODE' => Tools::getValue('THANAF_LIVE_MODE', Configuration::get('THANAF_LIVE_MODE')),
            'THANAF_VAT_NUMBER' => Tools::getValue('THANAF_VAT_NUMBER', Configuration::get('THANAF_VAT_NUMBER')),
            'THANAF_COMPANY' => Tools::getValue('THANAF_COMPANY', Configuration::get('THANAF_COMPANY')),
            'THANAF_REG_AUTO' => Tools::getValue('THANAF_REG_AUTO', Configuration::get('THANAF_REG_AUTO')),
            'THANAF_REG' => Tools::getValue('THANAF_REG', Configuration::get('THANAF_REG')),
            'THANAF_POSTCODE_AUTO' => Tools::getValue('THANAF_POSTCODE_AUTO', Configuration::get('THANAF_POSTCODE_AUTO')),
            'THANAF_POSTCODE' => Tools::getValue('THANAF_POSTCODE', Configuration::get('THANAF_POSTCODE')),
            'THANAF_PHONE_AUTO' => Tools::getValue('THANAF_PHONE_AUTO', Configuration::get('THANAF_PHONE_AUTO')),
            'THANAF_PHONE' => Tools::getValue('THANAF_PHONE', Configuration::get('THANAF_PHONE')),
            'THANAF_STATE_AUTO' => Tools::getValue('THANAF_STATE_AUTO', Configuration::get('THANAF_STATE_AUTO')),
            'THANAF_STATE' => Tools::getValue('THANAF_STATE', Configuration::get('THANAF_STATE')),
            'THANAF_CITY_AUTO' => Tools::getValue('THANAF_CITY_AUTO', Configuration::get('THANAF_CITY_AUTO')),
            'THANAF_CITY' => Tools::getValue('THANAF_CITY', Configuration::get('THANAF_CITY')),
            'THANAF_ADDRESS_AUTO' => Tools::getValue('THANAF_ADDRESS_AUTO', Configuration::get('THANAF_ADDRESS_AUTO')),
            'THANAF_ADDRESS' => Tools::getValue('THANAF_ADDRESS', Configuration::get('THANAF_ADDRESS'))
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('configure') == $this->name) {
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        if (!Configuration::get('THANAF_LIVE_MODE')) {
            return false;
        }

        if ($this->getPsVersion() == 6) {
            $front_variables = $this->getFrontData();
            if ($front_variables) {
                $this->context->smarty->assign($front_variables);
                return $this->display(__FILE__, 'header.tpl');
            }
        }

        return false;
    }

    public function hookActionFrontControllerSetMedia($params)
    {
        if (!Configuration::get('THANAF_LIVE_MODE')) {
            return false;
        }

        $this->context->controller->addJS(_MODULE_DIR_.$this->name.'/views/js/front.js');

        if ($this->getPsVersion() == 7) {
            $front_variables = $this->getFrontData();
            if ($front_variables) {
                Media::addJsDef($front_variables);
            }
        }

        return false;
    }

    public function getFrontData()
    {
        $data = array(
            'tha_ajax_url' => $this->context->link->getModuleLink($this->name, 'ajax', array('token' => Tools::getToken(false))),
            'tha_vat_number' => Configuration::get('THANAF_VAT_NUMBER') ? Configuration::get('THANAF_VAT_NUMBER') : 'input[name="vat_number"]',
            'tha_company' => Configuration::get('THANAF_COMPANY') ? Configuration::get('THANAF_COMPANY') : 'input[name="company"]',
            'tha_dni' => Configuration::get('THANAF_REG_AUTO') ? Configuration::get('THANAF_REG') : false,
            'tha_postcode' => Configuration::get('THANAF_POSTCODE_AUTO') ? Configuration::get('THANAF_POSTCODE') : false,
            'tha_phone' => Configuration::get('THANAF_PHONE_AUTO') ? Configuration::get('THANAF_PHONE') : false,
            'tha_state' => Configuration::get('THANAF_STATE_AUTO') ? Configuration::get('THANAF_STATE') : false,
            'tha_city' => Configuration::get('THANAF_CITY_AUTO') ? Configuration::get('THANAF_CITY') : false,
            'tha_address' => Configuration::get('THANAF_ADDRESS_AUTO') ? Configuration::get('THANAF_ADDRESS') : false,
        );

        return $data;
    }

    public function curl($cui)
    {
        $response = array(
            'error' => false,
            'result' => ''
        );

        $curl = curl_init();

        $headers = array(
            "Content-Type: application/json"
        );

        $postfields = array();
        $postfields[] = array(
            'cui' => str_replace('ro', '', Tools::strtolower($cui)),
            'data' => date('Y-m-d')
        );

        $curl_data = array(
            CURLOPT_URL => self::TH_ANAF_API_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($postfields)
        );

        curl_setopt_array($curl, $curl_data);
        $result = json_decode(curl_exec($curl), true);
        curl_close($curl);

        if (isset($result['cod']) && $result['cod'] == '200' && $result['message'] == 'SUCCESS') {
            $response['result'] = $this->handleResponse($result);
        } else {
            $response['error'] = true;
            $response['result'] = $this->l('The request could not be sent. Try again later!');
        }

        return $response;
    }

    public function handleResponse($response)
    {
        $details = $response['found'][0];
        if ($details['scpTVA']) {
            $vat_payer = true;
        } else {
            $vat_payer = false;
        }

        $state = '';
        $city = '';
        $address = '';

        //handle address
        $full_address = $details['adresa'];
        $full_address = $this->replaceDiacritics($full_address);

        $exploded = explode(',', $full_address);

        foreach ($exploded as $key => $value) {
            if ($key == 0) {
                $state = $this->handleState($value);
            } elseif ($key == 1) {
                $city = $this->handleCity($value);
            } else {
                $address .= $value.',';
            }
        }

        if ($address) {
            $address = $this->handleAddress($address);
        }

        $postcode = '';
        if (isset($details['codPostal'])) {
            $postcode = $details['codPostal'];
        }

        $phone = '';
        if (isset($details['telefon'])) {
            $phone = $details['telefon'];
        }

        $result = array(
            'company' => $details['denumire'],
            'vat_payer' => $vat_payer,
            'reg_com' => $details['nrRegCom'],
            'postcode' => $postcode,
            'phone' => $phone,
            'state' => $state,
            'city' => $city,
            'address' => $address
        );

        return $result;
    }

    public function handleState($state)
    {
        $state = str_replace('JUD. ', '', $state);
        $state = str_replace('MUNICIPIUL ', '', $state);
        $state = $this->capitalizeText($state);

        return $state;
    }

    public function handleCity($city)
    {
        $city = trim($city);

        if (strpos($city, 'SECTOR') !== false) {
            $city = 'Bucuresti';
        } else {
            $exploded_city = explode(' ', $city);

            if (isset($exploded_city[1]) && $exploded_city[1]) {
                $city = $this->capitalizeText($exploded_city[1]);
            }
        }

        return $city;
    }

    public function handleAddress($address)
    {
        $address = trim($address);
        $address = Tools::substr($address, 0, -1);
        $address = $this->capitalizeText($address);

        return $address;
    }

    public function replaceDiacritics($in)
    {
        $unwanted_array = array(
            'Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
            'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U',
            'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c',
            'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o',
            'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y', 'ő' =>'o', 'ü'=>'u',
            'ă'=>'a', 'ă'=>'a', 'Ă'=>'A', 'ș'=>'s', 'ş'=>'s', 'Ș'=>'S', 'Ş'=>'S', 'ț'=>'t', 'Ț'=>'T', 'Ţ' => 'T', 'Á'=>'A', 'á'=>'a', 'ţ'=>'t', 'ț'=>'t', '"' => '`', '\'' => '`'

        );

        return strtr($in, $unwanted_array);
    }

    public function capitalizeText($text)
    {
        if (!$text) {
            return $text;
        }

        return ucwords(Tools::strtolower($text));
    }

    public function getPsVersion()
    {
        $full_version = _PS_VERSION_;
        return explode(".", $full_version)[1];
    }
}
