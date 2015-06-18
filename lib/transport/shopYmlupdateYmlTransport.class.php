<?php

/**
 * Class shopYmlupdateYmlTransport
 * @description Import data from file in YandexMarketLanguage format
 * @group YML
 */
class shopYmlupdateYmlTransport extends shopYmlupdateTransport {

    const STAGE_CURRENCY = 'currency';

    private static $node_map = array(
        "yml_catalog/shop/offers" => self::STAGE_PRODUCT,
    );
    private static $node_name_map = array(
        self::STAGE_PRODUCT => array(
            'offer',
            'next'
        ),
    );
    private $replace_skus = array();

    /**
     * @var XMLReader
     */
    private $reader;
    private $path = array();

    protected function getStepMethod($stage) {
        $methods = array(
            'step' . ucfirst($stage),
        );
        $method_name = null;
        foreach ($methods as $method) {
            if (method_exists($this, $method)) {
                $method_name = $method;
                break;
            }
        }
        if (!$method_name) {
            $this->log(sprintf("Unsupported actions %s", implode(', ', $methods)), self::LOG_ERROR);
        }

        return $method_name;
    }

    public function getStageName($stage) {
        switch ($stage) {
            case self::STAGE_CURRENCY:
                $name = _wp('Importing currencies...');
                break;
            default:
                $name = parent::getStageName($stage);
                break;
        }

        return $name;
    }

    public function getStageReport($stage, $data) {
        $report = '';
        if (!empty($data[$stage])) {
            $count = $data[$stage];
            switch ($stage) {
                case self::STAGE_CURRENCY:
                    $report = _wp('%d currency', '%d currencies', $count);
                    break;
                default:
                    $report = parent::getStageReport($stage, $data);
            }
        }

        return $report;
    }

    private function uploadYml() {
        $url = $this->getOption('url');
        if (empty($url)) {
            throw new waException(_wp('Empty URL for YML'));
        } else {
            $name = parse_url($url, PHP_URL_HOST) . '.xml';
            $path = wa()->getTempPath('plugins/ymlupdate/yml/' . $name);
            try {
                waFiles::upload($url, $path);
            } catch (waException $ex) {
                $this->log($ex->getMessage(), self::LOG_ERROR, compact('url', 'path'));
                throw new waException(sprintf(_wp('Error while upload YML file: %s'), $ex->getMessage()));
            }
        }

        return $name;
    }

    public function validate($result, &$errors, $profile_id = null) {
        try {
            $file = $this->uploadYml();
            $option = array(
                'readonly' => true,
                'valid' => true,
            );
            $this->addOption('url', $option);
            $this->addOption('type', $this->getProductTypeOption());
            $this->addOption('path', array('value' => $file));

            $not_found_file = wa()->getTempPath('plugins/ymlupdate/download/' . $profile_id . '/not_found_file.csv');
            @unlink($not_found_file);
            $this->addOption('not_found_file', array('value' => $not_found_file));
        } catch (waException $ex) {
            $result = false;
            $errors['url'] = $ex->getMessage();
            $this->addOption('url', array('readonly' => false));
        }

        return parent::validate($result, $errors);
    }

    private function getReadOptions() {
        
    }

    public function count() {

        $counts = array();
        $method = null;
        $this->openXml();
        while ($this->read($method)) {
            $method = 'unknown_count';
            if ($this->reader->depth >= 2) {
                if ($stage = $this->getStage()) {
                    list($node, $method) = self::$node_name_map[$stage];
                    if ($method == 'next') {
                        $map = array_flip(self::$node_map);
                        $path = ifset($map[$stage], '/') . '/' . $node;
                    } else {
                        $path = null;
                    }
                    while (($current_stage = $this->getStage())) {
                        if ($current_stage != $stage) {
                            $stage = $current_stage;
                            list($node, $method) = self::$node_name_map[$stage];
                            if ($method == 'next') {
                                $map = array_flip(self::$node_map);
                                $path = ifset($map[$stage], '/') . '/' . $node;
                            } else {
                                $path = null;
                            }
                        }
                        if (!isset($counts[$stage])) {
                            $counts[$stage] = 0;
                            $method_ = 'read';
                        } else {
                            $method_ = $method;
                        }

                        if ($this->read($method_, $path)) {
                            if ($this->reader->nodeType == XMLReader::ELEMENT) {
                                if ($this->reader->name == $node) {
                                    ++$counts[$stage];
                                }
                            }
                        } else {
                            $method = 'end_count';
                            $this->read($method);
                            break 2;
                        }
                    }
                }
                $method = 'next';
            }
        }
        $this->reader->close();
        $this->log($counts, self::LOG_DEBUG);

        return $counts;
    }

    /**
     * @param string[] $path XPath
     * @return string Import stage name
     */
    private function getStage($path = null) {
        $stage = null;
        $node_path = implode('/', array_slice($path ? $path : $this->path, 0, 3));
        if (isset(self::$node_map[$node_path])) {
            $stage = self::$node_map[$node_path];
        }

        return $stage;
    }

    public function step(&$current, &$count, &$processed, $current_stage, &$error) {
        static $read_method = null;
        static $offset = array();

        $result = true;
        $stage = null;
        try {
            $chunk = 30;

            while ($while = $this->read($read_method)) {
                $read_method = 'unknown_import';
                if ($this->reader->depth >= 2) {
                    if ($stage = $this->getStage()) {

                        $method_name = $this->getStepMethod($stage);
                        if (
                                $method_name && //method name determined for current node
                                ($current[$stage] < $count[$stage]) //node still not processed
                        ) {

                            list($node, $read_method) = self::$node_name_map[$stage];
                            if ($read_method == 'next') {
                                $map = array_flip(self::$node_map);
                                $path = ifset($map[$stage], '/') . '/' . $node;
                            } else {
                                $path = null;
                            }

                            while (($cur_stage = $this->getStage()) && ($cur_stage == $stage)) {

                                if (!isset($offset[$stage])) {
                                    $offset[$stage] = 0;
                                    $internal_read_method = 'read';
                                } else {
                                    $internal_read_method = $read_method;
                                }

                                if ($this->read($internal_read_method, $path)) {
                                    if ($this->reader->nodeType == XMLReader::ELEMENT) {
                                        if ($this->reader->name == $node) {
                                            ++$offset[$stage];
                                            if ($current[$stage] < $offset[$stage]) {
                                                $result = $this->$method_name($current[$stage], $count, $processed[$stage]);
                                                if ($current[$stage] && ($current[$stage] === $count[$stage])) {
                                                    $complete_method = 'complete' . ucfirst($stage);
                                                    if (method_exists($this, $complete_method)) {
                                                        $this->$complete_method();
                                                    }
                                                    $result = false;
                                                }
                                                if (!$result) {
                                                    break 2;
                                                }
                                                if (--$chunk <= 0) {
                                                    $read_method = 'skip';
                                                    break 2;
                                                }
                                            }
                                        }
                                    }
                                } else {
                                    $read_method = 'end';
                                    $this->read($read_method);
                                    break 2;
                                }
                            }
                        }
                    }
                    $read_method = 'next';
                }
            }




            if ($r = $this->getXmlError()) {
                $this->log('XML errors while read: ' . $r, self::LOG_ERROR);
            }
        } catch (Exception $ex) {
            $this->stepException($current, $stage, $error, $ex);
        }

        return ifempty($result);
    }

    private function prepareSkus($skus) {
        foreach ($skus as &$sku) {
            if (!$sku['stock']) {
                $sku['stock'][0] = is_null($sku['count']) ? '' : $sku['count'];
            } else {
                foreach ($sku['stock'] as &$stock) {
                    $stock = is_null($stock) ? '' : $stock;
                }
            }
        }
        return $skus;
    }

    private function getStocks($sku_id) {
        $stocks = array();
        $product_stocks = new shopProductStocksModel();
        $return = $product_stocks->getBySkuId($sku_id);
        if (isset($return[$sku_id])) {
            foreach ($return[$sku_id] as $stock_id => $stock) {
                $stocks[$stock_id] = is_null($stock['count']) ? '' : $stock['count'];
            }
        }
        return $stocks;
    }

    private function productUpdate($sku_id, $product, $element) {
        $price = self::field($element, 'price', 'double');
        $margin = $this->getOption('margin');

        $update_data = array(
            'price' => $price * (1 + $margin / 100),
            'stock' => $this->getStocks($sku_id),
            'available' => self::attribute($element, 'available') ? 1 : 0,
        );
        $stock_id = $this->getOption('stock_id');

        if (self::attribute($element, 'available') === 'true') {
            $stock = $this->getOption('stock');
        } else {
            $stock = 0;
        }
        $update_data['stock'][$stock_id] = $stock;

        $product_model = new shopProductModel();
        $model_sku = new shopProductSkusModel();
        $data = $product_model->getById($product->id);
        $data['skus'] = $model_sku->getDataByProductId($product->id, true);

        $data['skus'] = $this->prepareSkus($data['skus']);
        $data['skus'][$sku_id] = array_merge($data['skus'][$sku_id], $update_data);

        $product->save($data, true);
    }

    private function addNotFound($name, $vendorCode) {
        $not_found_file = $this->getOption('not_found_file');
        $f = fopen($not_found_file, 'a+');
        $name = iconv('UTF-8', 'CP1251', $name);
        $vendorCode = iconv('UTF-8', 'CP1251', $vendorCode);
        fputcsv($f, array($name, $vendorCode), ';', '"');
        fclose($f);
    }

    private function getReplaceSkus() {
        if (empty($this->replace_skus)) {
            $model = new shopYmlupdatePluginModel();
            $skus = $model->getAll();
            foreach ($skus as $sku) {
                $sku_file = $sku['sku_file'];
                $sku_site = $sku['sku_site'];
                $this->replace_skus[$sku_file] = $sku_site;
            }
        }
        return $this->replace_skus;
    }

    private function replaceSku($sku_file) {
        $skus = $this->getReplaceSkus();
        if (!empty($skus[$sku_file])) {
            return $skus[$sku_file];
        }
        return $sku_file;
    }

    private function stepProduct(&$current_stage, &$count, &$processed) {

        $element = $this->element();
        $name = self::field($element, 'name');
        $vendorCode = self::field($element, 'vendorCode');
        $vendorCode = $this->replaceSku($vendorCode);

        $hash = array('search', 'query=' . $vendorCode);
        $collection = new shopProductsCollection(implode('/', $hash));

        $products = $collection->getProducts('*', 0, $count);
        $search_product = reset($products);

        $not_found = true;
        if (!empty($search_product['id'])) {
            $product = new shopProduct($search_product['id']);

            foreach ($product->skus as $sku) {
                if ($sku['sku'] == $vendorCode) {
                    $this->productUpdate($sku['id'], $product, $element);
                    $not_found = false;
                }
            }
        }

        if ($not_found) {
            $this->addNotFound($name, $vendorCode);
        }

        ++$current_stage;
        ++$processed;

        return true || $current_stage < $count[self::STAGE_PRODUCT];
    }

    private function completeProduct() {
        if (isset($this->map[self::STAGE_CATEGORY])) {
            unset($this->map[self::STAGE_CATEGORY]);
        }
    }

    /**
     *
     * @return SimpleXMLElement
     * @throws waException
     */
    private function element() {
        if (!$this->reader) {
            throw new waException('Empty XML reader');
        }
        $element = $this->reader->readOuterXml();


        return simplexml_load_string(trim($element));
    }

    /**
     * @param SimpleXMLElement $element
     * @param string $xpath
     * @return SimpleXMLElement[]
     */
    private function xpath($element, $xpath) {
        if ($namespaces = $element->getNamespaces(true)) {
            $name = array();
            foreach ($namespaces as $id => $namespace) {
                $element->registerXPathNamespace($name[] = 'wa' . $id, $namespace);
            }
            $xpath = preg_replace('@(^[/]*|[/]+)@', '$1' . implode(':', $name) . ':', $xpath);
        }

        return $element->xpath($xpath);
    }

    /**
     *
     *
     * @param SimpleXMLElement $element
     * @param string $field
     * @param string $type
     *
     * @return mixed
     */
    private static function field(&$element, $field, $type = 'string') {
        $value = $element->{$field};
        switch ($type) {
            case 'xml':
                break;
            case 'intval':
            case 'int':
                $value = intval(
                        str_replace(
                                array(
                    ' ',
                    ','
                                ), array(
                    '',
                    '.'
                                ), (string) $value
                        )
                );
                break;
            case 'floatval':
            case 'float':
                $value = floatval(
                        str_replace(
                                array(
                    ' ',
                    ','
                                ), array(
                    '',
                    '.'
                                ), (string) $value
                        )
                );
                break;
            case 'doubleval':
            case 'double':
                $value = doubleval(
                        str_replace(
                                array(
                    ' ',
                    ','
                                ), array(
                    '',
                    '.'
                                ), (string) $value
                        )
                );
                break;
            case 'array':
                $value = (array) $value;
                break;
            case 'string':
            default:
                $value = trim((string) $value);
                break;
        }

        return $value;
    }

    /**
     * @param SimpleXMLElement $element
     * @param string $attribute
     * @return string
     */
    private static function attribute(&$element, $attribute) {
        $value = (string) $element[$attribute];
        $value = preg_replace_callback(
                '/\\\\u([0-9a-f]{4})/i', array(
            __CLASS__,
            'replaceUnicodeEscapeSequence'
                ), $value
        );
        $value = preg_replace_callback(
                '/\\\\u([0-9a-f]{4})/i', array(
            __CLASS__,
            'htmlDereference'
                ), $value
        );

        return $value;
    }

    private static function htmlDereference($match) {
        if (strtolower($match[1][0]) === 'x') {
            $code = intval(substr($match[1], 1), 16);
        } else {
            $code = intval($match[1], 10);
        }

        return mb_convert_encoding(pack('N', $code), 'UTF-8', 'UTF-32BE');
    }

    private static function replaceUnicodeEscapeSequence($match) {
        return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
    }

    private function openXml() {
        if ($this->reader) {
            $this->reader->close();
        } else {
            $this->reader = new XMLReader();
        }
        $name = $this->getOption('path');
        $path = wa()->getTempPath('plugins/ymlupdate/yml/' . $name);
        if (!file_exists($path) && is_file($path)) {
            throw new waException('XML file missed');
        }

        libxml_use_internal_errors(true);
        libxml_clear_errors();
        if (!@$this->reader->open($path, null, LIBXML_NONET)) {
            $this->log('Error while open XML ' . $path, self::LOG_ERROR);
            throw new waException('Ошибка открытия XML файла');
        }
    }

    protected function getXmlError() {
        $messages = array();
        $errors = libxml_get_errors();
        /**
         * @var LibXMLError[] $errors
         */
        foreach ($errors as $error) {
            $messages[] = sprintf('#%d@%d:%d %s', $error->level, $error->line, $error->column, $error->message);
        }
        libxml_clear_errors();

        return implode("\n", $messages);
    }

    private function path() {
        $node = (string) $this->reader->name;
        $depth = (int) $this->reader->depth;

        $this->path = array_slice($this->path, 0, $depth);
        $this->path[$depth] = $node;
        if ($depth) {
            $this->path += array_fill(0, $depth, '—');
        }

        return $this->path;
    }

    private function read($method = 'read', $node = null) {
        if (!$this->reader) {
            $this->openXml();
        }
        $result = null;
        switch ($method) {
            case 'skip':
                $result = true;
                break;
            case 'next':
                if ($node) {
                    $base = explode('/', $node);
                    $name = array_pop($base);
                    $depth = count($base);
                    $base = implode('/', $base);

                    do {
                        $result = $this->read($method, false);
                        $path = implode('/', array_slice($this->path, 0, $depth));
                    } while (
                    $result &&
                    ($path == $base) &&
                    (($this->reader->nodeType != XMLReader::ELEMENT) || ($this->reader->name != $name))
                    );
                } else {
                    $result = $this->reader->next();
                }
                break;
            case 'read':
            default:
                $result = $this->reader->read();
                break;
        }
        $this->path();

        return $result;
    }

    protected function getContextDescription() {
        $url = $this->getOption('url');
        $url = parse_url($url, PHP_URL_HOST);
        return empty($url) ? '' : sprintf(_wp('Import data from %s'), $url);
    }

}
