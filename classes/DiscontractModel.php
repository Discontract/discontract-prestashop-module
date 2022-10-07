<?php

if (!defined('_PS_VERSION_'))
  exit;

class DiscontractModel
{
  public static $instance;
  public static function getInstance()
  {
    if (!(isset(self::$instance)) || !self::$instance) {
      self::$instance = new DiscontractModel();
    }
    return self::$instance;
  }
  public function getOrderStates($languageId) {
    $sql = sprintf(
      'SELECT id_order_state as id_option, name FROM `%s` WHERE id_lang = %d',
      _DB_PREFIX_ . 'order_state_lang',
      (int)$languageId
    );
    // echo $sql;
    $states = Db::getInstance()->executeS($sql);
    return $states;
  }
  public function removeDiscontractProductsFromInvoice($orderInvoice) {
    $orderId = $orderInvoice->id_order;
    $invoiceId = $orderInvoice->id;
    // var_dump($orderId);
    $sql = sprintf(
      'SELECT * FROM %s WHERE id_order = %d',
      _DB_PREFIX_ . 'order_detail',
      (int)$orderId
    );
    $products = Db::getInstance()->executeS($sql);
    // var_dump($$products);
    $substractTotal = 0;
    for ($i = 0; $i < count($products); $i++) {
      $product = $products[$i];
      $productId = $product['product_id'];
      $sql = sprintf(
        'SELECT * FROM %s WHERE id_product = %d',
        _DB_PREFIX_ . 'discontract_job_product',
        (int)$productId
      );
      $job = Db::getInstance()->getRow($sql);
      if ($job) {
        $substractTotal += $product["total_price_tax_incl"];
        $sql = sprintf(
          'UPDATE %s SET `id_order_invoice` = 0 WHERE product_id = %d AND id_order = %d',
          _DB_PREFIX_ . 'order_detail',
          (int)$productId,
          (int)$orderId
        );
        Db::getInstance()->execute($sql);
      }
    }
    if ($substractTotal > 0) {
      $total_paid_tax_excl = $orderInvoice->total_paid_tax_excl - $substractTotal;
      $total_paid_tax_incl = $orderInvoice->total_paid_tax_incl - $substractTotal;
      $total_products = $orderInvoice->total_products - $substractTotal;
      $total_products_wt = $orderInvoice->total_products_wt - $substractTotal;
      $sql = sprintf(
        'UPDATE %s SET `total_paid_tax_excl` = %f, `total_paid_tax_incl` = %f, `total_products` = %f, `total_products_wt` = %f WHERE id_order_invoice = %d',
        _DB_PREFIX_ . 'order_invoice',
        (float) $total_paid_tax_excl,
        (float) $total_paid_tax_incl,
        (float) $total_products,
        (float) $total_products_wt,
        (int)$invoiceId
      );
      Db::getInstance()->execute($sql);
      // var_dump($invoiceId);
      // die($invoiceId);
    }
  }
  public function getOrderInfo($orderId)
  {
    $sql = 'SELECT o.`id_cart`, o.`id_address_delivery`, o.`id_address_invoice`,
      ai.`company`, ai.`dni`, ai.`vat_number`, ai.`firstname`, ai.`lastname`, ai.`address1`, ai.`address2`, ai.`city`, ai.`phone`, ai.`vat_number`,
      ad.`company` ad_company, ad.`firstname` ad_firstname, ad.`lastname` ad_lastname, ad.`address1` ad_address1, ad.`address2` ad_address2, ad.`city` ad_city, ad.`phone` ad_phone, ad.`vat_number` ad_vat_number,
      c.`email`
      FROM `' . _DB_PREFIX_ . 'orders` AS o 
      JOIN `' . _DB_PREFIX_ . 'address` AS ad ON o.`id_address_delivery` = ad.`id_address`
      JOIN `' . _DB_PREFIX_ . 'address` AS ai ON o.`id_address_invoice` = ai.`id_address`
      JOIN `' . _DB_PREFIX_ . 'customer` AS c ON o.`id_customer` = c.`id_customer`
      WHERE `id_order` = ' . (int)$orderId . '';
    $row = Db::getInstance()->getRow($sql);
    return $row;
  }
  public function isProductDiscontractService($productId)
  {
    $categoryId = (int)Configuration::get('DISCONTRACT_CATEGORY_ID');
    if ($categoryId === 0) {
      return false;
    }
    $sql = sprintf('SELECT * FROM `%s` WHERE id_product = %d AND id_category = %d', _DB_PREFIX_ . 'category_product', (int) $productId, $categoryId);
    $row = Db::getInstance()->getRow($sql);
    if ($row) {
      return true;
    } else {
      return false;
    }
  }
  public function getProductTitles($productId)
  {
    $sql = sprintf(
      'SELECT * FROM %s WHERE id_product = %d',
      _DB_PREFIX_ . 'product_lang',
      (int)$productId
    );
    return Db::getInstance()->getRow($sql);
  }
  public function getDiscontractCustomizationsForCart($cartId)
  {
    $module = Module::getInstanceByName('discontractpro');
    $sql = sprintf('SELECT c.id_customization, cd.value, cp.quantity 
                    FROM `' . _DB_PREFIX_ . 'customized_data` as cd
                    INNER JOIN `' . _DB_PREFIX_ . 'customization` as c ON cd.id_customization = c.id_customization
                    INNER JOIN `' . _DB_PREFIX_ . 'cart_product` as cp ON cp.id_customization = c.id_customization
                WHERE cd.id_module= %d AND cp.id_cart = %d;', (int) $module->id, (int) $cartId);
    // var_dump($sql);
    // die();
    $response = Db::getInstance()->executeS($sql);
    return $response;
  }
  public function addProductToCart($cartId, $productId, $order, $customizationId, $quantity)
  {
    // TODO: add distnce price via customization
    $sql = sprintf(
      'DELETE FROM %s WHERE id_cart = %d AND id_product = %d',
      _DB_PREFIX_ . 'specific_price',
      (int)$cartId,
      (int)$productId
    );
    Db::getInstance()->execute($sql);
    $sql = sprintf(
      'INSERT INTO %s (id_cart, id_product, id_shop, price) VALUES (%d, %d, %d, %f)',
      _DB_PREFIX_ . 'specific_price',
      (int)$cartId,
      (int)$productId,
      1,
      (float)($order->price->jobCost) / 100
    );
    Db::getInstance()->execute($sql);
    $sql = sprintf(
      'INSERT INTO %s (id_cart, id_product, id_shop, quantity, id_customization) VALUES (%d, %d, %d, %d, %d)',
      _DB_PREFIX_ . 'cart_product',
      (int)$cartId,
      (int)$productId,
      1,
      (int)$quantity,
      $customizationId
    );
    Db::getInstance()->execute($sql);
  }
  public function createDiscontractCustomization($cartId, $productId, $quantity, $value, $cost)
  {
    $module = Module::getInstanceByName('discontractpro');
    $sql = sprintf(
      'INSERT INTO %s (id_cart, id_product, quantity, in_cart) VALUES (%d, %d, %d, %d)',
      _DB_PREFIX_ . 'customization',
      (int)$cartId,
      (int)$productId,
      (int)$quantity,
      1
    );
    Db::getInstance()->execute($sql);
    $customizationId = Db::getInstance()->Insert_ID();
    $val = Db::getInstance()->escape($value);
    $sql = sprintf(
      'INSERT INTO %s (id_customization, value, type, id_module, price) VALUES (%d, "%s", %d, %d, %f)',
      _DB_PREFIX_ . 'customized_data',
      (int)$customizationId,
      $val,
      1,
      $module->id,
      $cost
    );
    Db::getInstance()->execute($sql);
    return $customizationId;
  }
  public function detachDiscontractCart($cartId)
  {
    $sql = sprintf('DELETE FROM `%s` WHERE id_cart = %d', _DB_PREFIX_ . 'discontract_cart', (int)$cartId);
    Db::getInstance()->execute($sql);
  }
  public function attachDiscontractCart($cartId, $discotractCartId, $status)
  {
    $sql = sprintf(
      'INSERT INTO `%s` (id_cart, id_discontract_cart, status) VALUES (%d, "%s", "%s")',
      _DB_PREFIX_ . 'discontract_cart',
      (int)$cartId,
      Db::getInstance()->escape($discotractCartId),
      Db::getInstance()->escape($status)
    );
    Db::getInstance()->execute($sql);
  }
  public function getDiscontractCart($cartId)
  {
    $sql = sprintf(
      'SELECT * FROM `%s` WHERE id_cart = %d',
      _DB_PREFIX_ . 'discontract_cart',
      (int)$cartId
    );
    // echo $sql;
    $row = Db::getInstance()->getRow($sql);
    return $row;
  }
  public function updateCartStatus($cartId, $status)
  {
    $sql = sprintf(
      'UPDATE %s SET `status` = "%s" WHERE id_cart = %d',
      _DB_PREFIX_ . 'discontract_cart',
      Db::getInstance()->escape($status),
      (int)$cartId
    );
    Db::getInstance()->execute($sql);
  }
  public function updateCustomizationPrice($customizationId, $price)
  {
    $id_module = Module::getInstanceByName('discontractpro')->id;
    $sql = sprintf(
      'UPDATE %s SET price = %f WHERE id_customization = %d AND id_module = %d',
      _DB_PREFIX_ . 'customized_data',
      (float)$price,
      (int)$customizationId,
      (int)$id_module
    );
    Db::getInstance()->execute($sql);
  }
  public function setProductJobId($productId, $jobId)
  {
    $sqls[] = 'DELETE FROM `' . _DB_PREFIX_ . 'discontract_job_product` WHERE id_product = ' . (int)$productId;
    $sqls[] = 'INSERT INTO `' . _DB_PREFIX_ . 'discontract_job_product` (id_product, id_discontract_job) VALUES (' . (int)$productId . ',"' . Db::getInstance()->escape($jobId) . '")';
    foreach ($sqls as $sql) {
      Db::getInstance()->execute($sql);
    }
  }
  public function getJobIdByProductCategories($productId)
  {
    $categoryId = (int)Configuration::get('DISCONTRACT_CATEGORY_ID');
    if ($categoryId === 0) {
      return false;
    }
    // get all categories for product
    $sql = 'SELECT cp.id_category, ca.is_root_category FROM `' . _DB_PREFIX_ . 'category_product` as cp LEFT JOIN '._DB_PREFIX_.'category AS ca ON cp.id_category = ca.id_category WHERE id_product = ' . (int)$productId;
    $categories = Db::getInstance()->executeS($sql);
    // var_dump($categories);
    // die();

    // get all product/job relations (discontract products)
    $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'discontract_job_product`';
    $jobs = Db::getInstance()->executeS($sql);

    $categoryIds = '';
    for ($i = 0; $i < count($categories); $i++) {
      if ((int)$categories[$i]['id_category'] === 0 || (int)$categories[$i]['is_root_category'] === 1) {
        continue;
      }
      if ((int)$categories[$i]['id_category'] != $categoryId) {
        if ($categoryIds != '') {
          $categoryIds .= ',';
        }
        $categoryIds .= (int)$categories[$i]['id_category'];
      }
    }
    if ($categoryIds === '') {
      return false;
    }

    // get all product ids that fall in the same categories as this product
    $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'category_product` WHERE id_category IN (' . $categoryIds . ')';
    $allprod = Db::getInstance()->executeS($sql);

    // check if any products that fall in same categories are discontract service products
    $added = array();
    $response = array();
    for ($i = 0; $i < count($allprod); $i++) {
      $row = $allprod[$i];
      for ($j = 0; $j < count($jobs); $j++) {
        // if ($jobs[$j]['id_product'] === $row['id_product'] && ((int)$row['id_product'] != (int)$productId)) {
        if ($jobs[$j]['id_product'] === $row['id_product']) {
          // die($row['id_product']);
          if (!isset($added[$jobs[$j]['id_discontract_job']])) {
            $item = new stdClass();
            $item->jobId = $jobs[$j]['id_discontract_job'];
            $item->productId = (int)$row['id_product'];
            $response[] = $item;
            $added[$item->jobId] = true;
          }
        }
      }
    }
    return $response;
  }
  public function getJobIdByProductId($productId)
  {
    $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'discontract_job_product` WHERE id_product = ' . (int)$productId;
    $row = Db::getInstance()->getRow($sql);
    if ($row) {
      // var_dump('here');
      return $row['id_discontract_job'];
    }
  }
  public function getJob($jobId)
  {
    $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'discontract_job` WHERE id_discontract_job = "' . Db::getInstance()->escape($jobId) . '"';
    $row = Db::getInstance()->getRow($sql);
    return $row;
  }
  public function deleteJobs()
  {
    $sql = 'DELETE FROM ' . _DB_PREFIX_ . 'discontract_job';
    Db::getInstance()->execute($sql);
  }
  public function updateDiscontractJob($job, $shopTranslation = "")
  {
    if (!property_exists($job, 'title')) {
      return;
    }
    $jobId = Db::getInstance()->escape($job->id);
    $jobTitleLt = Db::getInstance()->escape($job->title);
    $shopTranslation = Db::getInstance()->escape($shopTranslation);
    $sql = sprintf('SELECT * FROM `' . _DB_PREFIX_ . 'discontract_job` WHERE id_discontract_job = "%s"', $jobId);
    $row = Db::getInstance()->getRow($sql);
    if ($row) {
      $sql = sprintf(
        'UPDATE %s SET title_lt = "%s", price = %d, title_shop_lt = "%s" WHERE id_discontract_job = "%s"',
        _DB_PREFIX_ . 'discontract_job',
        $jobTitleLt,
        (int)$job->price->unitPrice,
        $shopTranslation,
        $jobId
      );
      Db::getInstance()->execute($sql);
    } else {
      $sql = sprintf(
        'INSERT INTO %s (id_discontract_job, title_lt, price, title_shop_lt) VALUES ("%s", "%s", %d, "%s")',
        _DB_PREFIX_ . 'discontract_job',
        $jobId,
        $jobTitleLt,
        (int)$job->price->unitPrice,
        $shopTranslation
      );
      Db::getInstance()->execute($sql);
    }
  }
  public function getDiscontractJobs()
  {
    $sql = sprintf('SELECT * FROM %s', _DB_PREFIX_ . 'discontract_job ORDER BY title_lt ASC');
    $response = Db::getInstance()->executeS($sql);
    return $response;
  }
  public function _installDb()
  {
    $sqls = array();
    $sqls[] = 'ALTER TABLE `' . _DB_PREFIX_ . 'customized_data' . '` modify COLUMN `value` varchar(5000)';
    $sqls[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'discontract_job_product` ( 
            `id_discontract_job` VARCHAR(255) NOT NULL , 
            `id_product` INT(11) NOT NULL ,
            PRIMARY KEY (`id_product`)) ENGINE = ' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci';
    $sqls[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'discontract_job` ( 
            `id_discontract_job` VARCHAR(255) NOT NULL, 
            `title_lt` VARCHAR(2550) NOT NULL,
            `price` INT(11) NOT NULL,
            `title_shop_lt` VARCHAR(2550),
            PRIMARY KEY (`id_discontract_job`)) ENGINE = ' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci';
    $sqls[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'discontract_cart` ( 
            `id_discontract_cart` VARCHAR(255) NOT NULL , 
            `id_cart` INT(11) NOT NULL ,
            `status` VARCHAR(255),
            PRIMARY KEY (`id_discontract_cart`, `id_cart`)) ENGINE = ' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci';
    foreach ($sqls as $sql) {
      Db::getInstance()->execute($sql);
    }
    return true;
  }

  public function _uninstallDb()
  {
    // Db::getInstance()->execute('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'discontract_job_product');
    // Db::getInstance()->execute('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'discontract_job');
    // Db::getInstance()->execute('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'discontract_cart');
    return true;
  }
}
