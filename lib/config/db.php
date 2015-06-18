<?php

/**
 * @author wa-plugins.ru <support@wa-plugins.ru>
 * @link http://wa-plugins.ru/
 */
return array(
    'shop_ymlupdate' => array(
        'sku_site' => array('varchar', 255, 'null' => 0, 'default' => ''),
        'sku_file' => array('varchar', 255, 'null' => 0, 'default' => ''),
        ':keys' => array(
            'sku_site' => 'sku_site',
            'sku_file' => 'sku_file',
        ),
    ),
);
