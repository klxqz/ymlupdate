<?php

class shopYmlupdatePluginUploadController extends shopUploadController {

    protected function save(waRequestFile $file) {

        try {
            $path = wa()->getTempPath('plugins/ymlupdate/csv/');
            waFiles::create($path);
            $original_name = $file->name;
            if ($name = tempnam($path, 'csv')) {
                unlink($name);
                if (($ext = pathinfo($original_name, PATHINFO_EXTENSION)) && preg_match('/^\w+$/', $ext)) {
                    $name .= '.' . $ext;
                }
                $file->moveTo($name);
            } else {
                throw new waException(_w('Error file upload'));
            }

            $model = new shopYmlupdatePluginModel();
            $model->truncate();
            $f = fopen($name, 'r+');

            while (!feof($f)) {
                $fields = fgetcsv($f, null, ';', '"');

                if (!empty($fields[0]) && !empty($fields[1])) {
                    $model->insert(array('sku_file' => $fields[0], 'sku_site' => $fields[1]));
                    $i++;
                }
            }


            return array();
        } catch (waException $ex) {
            if ($this->reader) {
                $this->reader->delete(true);
            }
            throw $ex;
        }
    }

}
