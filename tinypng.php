<?php
/**
* 2007-2015 Deindo.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    Deindo Ideas SL <contacto@deindo.es>
*  @copyright 2007-2019 Deindo Ideas SL
*  @license   http://www.deindo.es
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

class Tinypng extends Module
{
    public function __construct()
    {
        $this->name = 'tinypng';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Deindo Ideas';
        $this->need_instance = 0;
        $this->module_key = '';
        $this->author_address = '0x8dd752a09299c668069f82278e69c5b24247136f';
        $this->bootstrap = true;

        $this->displayName = $this->l('TinyPNG');
        $this->description = $this->l('Smart PNG and JPEG compression.');
        

        $this->folder = _PS_PROD_IMG_DIR_;
        $this->formats = ImageType::getImagesTypes('products');
        $this->images_to_opt = array();
        $this->images_opt = array();
        $this->images_types = array();

        parent::__construct();
    }

    public function install()
    {
        if (!parent::install()
            || !Db::getInstance()->Execute('
            CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'image_optimized` (
                `id_image` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `image` varchar(255) NOT NULL,                
                PRIMARY KEY  (`id_image`)
            ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci')) {
            return false;
        }

        return true;
    }

    public function getProductImagesRecursive($folder = '')
    {
        foreach (scandir($this->folder.$folder) as $resource) {
            if ($resource == '.' || $resource == '..') {
                continue;
            }

            if (is_dir($this->folder.$folder.$resource)) {
                $this->getProductImagesRecursive($folder.$resource.'/');
            } else {
                $ext = pathinfo($this->folder.$folder.$resource, PATHINFO_EXTENSION);
                
                if ($ext == 'jpg' || $ext == 'png') {
                    $add = true;
                    foreach ($this->images_types as $type) {
                        $s = strrpos($resource, $type);
                        if ($s !== false) {
                            $add = false;
                            break;
                        }
                    }

                    // Its not a miniature and its not optimized before
                    if ($add && !isset($this->images_opt[$folder.$resource])) {
                        $this->images_to_opt[] = '"'.$folder.$resource.'"';
                    }
                }
            }
        }
    }

    public function getImagesOptimized()
    {
        $query = new DbQuery();
        $query->from('image_optimized');

        $res = Db::getInstance()->executeS($query);

        $this->images_opt = array();

        foreach ($res as $r) {
            $this->images_opt[$r['image']] = $r['image'];
        }
    }

    public function optimizeImage($image)
    {
        $path_image = realpath($this->folder.$image);
        $image_content = Tools::file_get_contents($path_image);
        $response = $this->curlWrap('https://api.tinify.com/shrink', $image_content, 'POST');

        if ($response['httpcode'] == 201) {
            $r = Tools::jsonDecode($response['content']);
            $s = $this->curlWrap($r->output->url, array(), 'GET');

            if ($s['httpcode'] == 200) {
                //TODO: GUARDAR LA IMAGEN COMPRIMIDA
                $fp = fopen(realpath($path_image), 'w');
                fwrite($fp, $s['content']);
                fclose($fp);

                Db::getInstance()->insert('image_optimized', array('image' => $image));
            }

            return $r;
        }
    }

    public function getContent()
    {
        $html_confirmation = '';

        if (Tools::isSubmit('submitConfig')) {
            $tinypng_apikey = Tools::getValue('tinypng_apikey', '');

            if (!empty($tinypng_apikey)) {
                Configuration::updateValue('TINYPNG_APIKEY', $tinypng_apikey);
                $html_confirmation = $this->displayConfirmation($this->l('Settings updated'));
            } else {
                $html_confirmation = $this->displayError($this->l('Required fields are empty'));
            }
        }

        if (Tools::isSubmit('submitClean')) {
            $clean_images = Tools::getValue('clean_images');

            if ($clean_images) {
                $html_confirmation = $this->displayConfirmation($this->l('DB images cleaned'));
                Db::getInstance()->delete('image_optimized');
            }
        }

        $link = new Link();
        $shop = $this->context->shop;
        $base = Configuration::get('PS_SSL_ENABLED') ? 'https://'.$shop->domain_ssl:'http://'.$shop->domain;

        $this->getImageTypes();
        $this->getImagesOptimized();
        $this->getProductImagesRecursive();

        $this->context->smarty->assign(array(
            'ruta_modulos' => $base.$shop->getBaseURI().'modules/'.$this->name.'/ajax/import.php',
            'prod_images' => implode(', ', $this->images_to_opt),
            'images_to_opt' => count($this->images_to_opt),
            'link_adminimages' => $link->getAdminLink('AdminImages')
        ));

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $html_confirmation.$this->renderForm().$this->renderFormOptimize().$this->renderFormClean().$output;
    }

    public function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Module Configuration'),
                    'icon' => 'icon-cog',
                ),
                'input' => array(
                    array(
                      'type' => 'text',
                      'label' => $this->l('TinyPNG API key'),
                      'desc' => $this->l('Put your TinyPNG Api key: https://tinypng.com/dashboard/api'),
                      'name' => 'tinypng_apikey',
                      'required' => true,
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $this->fields_form = array();

        $field_values = array();
        
        $field_values['tinypng_apikey'] = Configuration::get('TINYPNG_APIKEY');

        $helper->submit_action = 'submitConfig';
        $admin_url = $this->context->link->getAdminLink('AdminModules', false);
        $admin_url .= '&configure='.$this->name.'&tab_module='.$this->tab;
        $helper->currentIndex = $admin_url.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $field_values,
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($fields_form));
    }

    public function getImageTypes()
    {
        $imagesTypes = ImageType::getImagesTypes('products');

        foreach ($imagesTypes as $i) {
            $this->images_types[] = $i['name'];
        }
    }

    public function renderFormOptimize()
    {
        $s_options = array(
          array('id' => 'expo_on', 'value' => 1, 'label' => $this->l('Yes')),
          array('id' => 'expo_off', 'value' => 0, 'label' => $this->l('No')),
        );

        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Images Compression'),
                    'icon' => 'icon-group',
                ),
                'input' => array(
                    array(
                    'type' => 'switch',
                    'label' => $this->l('Optimize Images ?'),
                    'desc' => $this->l('Bulk Images to Optimize'),
                    'name' => 'run_optimize',
                    'required' => true,
                    'is_bool' => true,
                    'values' => $s_options,
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Optimize'),
                ),
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $this->fields_form = array();

        $field_values = array('run_optimize' => 0);

        $helper->submit_action = 'submitCustomers';
        $admin_url = $this->context->link->getAdminLink('AdminModules', false);
        $admin_url .= '&configure='.$this->name.'&tab_module='.$this->tab;
        $helper->currentIndex = $admin_url.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $field_values,
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($fields_form));
    }

    public function renderFormClean()
    {
        $s_options = array(
          array('id' => 'clean_on', 'value' => 1, 'label' => $this->l('Yes')),
          array('id' => 'clean_off', 'value' => 0, 'label' => $this->l('No')),
        );

        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('DB Images'),
                    'icon' => 'icon-group',
                ),
                'input' => array(
                    array(
                    'type' => 'switch',
                    'label' => $this->l('Clean optimized images database?'),
                    'desc' => $this->l('Use this action to re-optimize all images.'),
                    'name' => 'clean_images',
                    'required' => true,
                    'is_bool' => true,
                    'values' => $s_options,
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Clean DB'),
                ),
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $this->fields_form = array();

        $field_values = array('clean_images' => 0);

        $helper->submit_action = 'submitClean';
        $admin_url = $this->context->link->getAdminLink('AdminModules', false);
        $admin_url .= '&configure='.$this->name.'&tab_module='.$this->tab;
        $helper->currentIndex = $admin_url.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $field_values,
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($fields_form));
    }

    public function curlWrap($url, $data, $method, $content_type = null)
    {
        $content_type = is_null($content_type) ? 'application/json' : $content_type;

        $api_key = Configuration::get('TINYPNG_APIKEY');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_UNRESTRICTED_AUTH, true);
        curl_setopt($ch, CURLOPT_URL, $url);

        switch ($method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                break;
            case 'GET':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            default:
                break;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: Basic ".base64_encode('api:'.$api_key)));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                
        $output = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return array('content' => $output, 'httpcode' => (int)$httpcode);
    }
}
