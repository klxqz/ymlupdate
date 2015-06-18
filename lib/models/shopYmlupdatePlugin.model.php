<?php

/**
 * @author wa-plugins.ru <support@wa-plugins.ru>
 * @link http://wa-plugins.ru/
 */
class shopYmlupdatePluginModel extends waModel {

    protected $table = 'shop_ymlupdate';

    public function truncate() {
        $sql = "TRUNCATE TABLE {$this->table}";
        return $this->query($sql);
    }

}
