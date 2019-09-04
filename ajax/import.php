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

include_once(dirname(__FILE__).'/../../../config/config.inc.php');
include_once(dirname(__FILE__).'/../../../init.php');
include_once(dirname(__FILE__).'/../../../modules/tinypng/tinypng.php');

$image = Tools::getValue('c', 0);

if ($image) {
    $tinypng = new Tinypng();
    $resultado = $tinypng->optimizeImage($image);
    echo Tools::jsonEncode($resultado);
    die();
}
