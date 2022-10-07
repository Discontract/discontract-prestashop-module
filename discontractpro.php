<?php

if (!defined('_PS_VERSION_')) {
  exit;
}

require_once(dirname(__FILE__) . '/classes/DiscontractApi.php');
require_once(dirname(__FILE__) . '/classes/DiscontractModel.php');

use PrestaShopBundle\Form\Admin\Type\CommonAbstractType;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

use function Symfony\Component\DependencyInjection\Loader\Configurator\ref;

class Democustomfields17AdminForm extends CommonAbstractType
{
  public function buildForm(FormBuilderInterface $adminFormBuilder, array $options)
  {
    $this->addFieldsByHook($options['hookFieldsBuilder'], $adminFormBuilder, $options['module']);
  }

  private function addFieldsByHook(
    $hookFieldsBuilder,
    FormBuilderInterface $adminFormBuilder,
    Module $module
  ) {
    $hookFieldsBuilder
      ->addFields($adminFormBuilder, $module)
      ->add(
        $module->getModuleFormDatasID(), // used to check if datas come from Admin Product form
        'Symfony\Component\Form\Extension\Core\Type\HiddenType',
        ['data' => '1']
      );
  }

  public function configureOptions(OptionsResolver $resolver)
  {
    $resolver->setDefaults(array(
      'hookFieldsBuilder' => null,
      'module' => null,
      'allow_extra_fields' => true
    ));
  }
}

class HookDisplayAdminProductsExtraFieldsBuilder
{
  public function addFields(FormBuilderInterface $adminFormBuilder, Module $module): FormBuilderInterface
  {
    $jobs = DiscontractModel::getInstance()->getDiscontractJobs();
    $choices = [];
    $choices["Meistro paslauga nepasirinkta"] = 0;
    for ($i = 0; $i < count($jobs); $i++) {
      $choices[$jobs[$i]['title_shop_lt'] ? $jobs[$i]['title_shop_lt'] : $jobs[$i]['title_lt']] = $jobs[$i]['id_discontract_job'];
    }
    $adminFormBuilder
      ->add('discontract_job_id', ChoiceType::class, [
        'choices' => $choices,
        'label' => $module->l('Discontract'),
        'attr' => [
          'data-toggle' => 'select2',
          'data-minimumResultsForSearch' => 3
          // 'class' => 'my-custom-class',
        ]
      ]);

    return $adminFormBuilder;
  }
}

class Discontractpro extends Module
{
  public function __construct()
  {
    $this->name = 'discontractpro';
    $this->tab = 'front_office_features';
    $this->version = '0.1.9';
    $this->author = 'Discontract';
    $this->need_instance = 0;
    $this->ps_versions_compliancy = [
      'min' => '1.7',
      'max' => '1.7.99',
    ];
    $this->bootstrap = true;

    parent::__construct();

    $this->discontractCartRequestProcessed = false;

    $this->displayName = $this->l('Discontract Pro Module');
    $this->description = $this->l('This module allows to book pro services in checkout.');

    $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

    if (!Configuration::get('DISCONTRACT_API_KEY')) {
      $this->warning = $this->l('No Discontract API key provided');
    }
    if (!Configuration::get('DISCONTRACT_CATEGORY_ID')) {
      $this->warning = $this->l('No Discontract Category id provided');
    }
  }

  public function getLocales()
  {
    $sfContainer = $this->symfonyContainerInstance();
    return $sfContainer->get('prestashop.adapter.data_provider.language')->getLanguages();
  }

  public function getModuleFormDatasID()
  {
    return 'fields_from_' . $this->name . '_' . $this->id;
  }

  /**
   * This method handles the module's configuration page
   * @return string The page's HTML content 
   */
  public function getContent()
  {
    $output = '';
    // this part is executed only when the form is submitted
    if (Tools::isSubmit('submit' . $this->name)) {
      // retrieve the value set by the user
      $configValue = (string) Tools::getValue('DISCONTRACT_API_KEY');
      $apiUrl = (string) Tools::getValue('DISCONTRACT_API_URL');
      $categoryId = (int) Tools::getValue('DISCONTRACT_CATEGORY_ID');
      $purchasedStates = Tools::getValue('DISCONTRACT_PURCHASED_STATES');
      $deliveredStates = Tools::getValue('DISCONTRACT_DELIVERED_STATES');

      // check that the value is valid
      // var_dump($categoryId);
      // die($categoryId);
      if (empty($configValue) || empty($categoryId) || $categoryId === 0 || empty($apiUrl)) {
        // invalid value, show an error
        $output = $this->displayError($this->l('Invalid Configuration value'));
      } else {
        // value is ok, update it and display a confirmation message
        Configuration::updateValue('DISCONTRACT_API_URL', $apiUrl);
        Configuration::updateValue('DISCONTRACT_API_KEY', $configValue);
        Configuration::updateValue('DISCONTRACT_CATEGORY_ID', $categoryId);
        Configuration::updateValue('DISCONTRACT_PURCHASED_STATES', implode(',', $purchasedStates));
        Configuration::updateValue('DISCONTRACT_DELIVERED_STATES', implode(',', $deliveredStates));
        
        $output = $this->displayConfirmation($this->l('Settings updated'));
        $response = DiscontractApi::getInstance()->getJobs();
        $jobs = $response->jobs;
        DiscontractModel::getInstance()->deleteJobs();
        for ($i = 0; $i < count($jobs); $i++) {
          $job = $jobs[$i];
          $shopTranslation = (string) Tools::getValue(str_replace('.', '_', $job->id));
          // var_dump($job->id);
          // var_dump($shopTranslation);
          // die($shopTranslation);
          DiscontractModel::getInstance()->updateDiscontractJob($job, $shopTranslation);
        }
      }
    }
    // display any message, then the form
    return $output . $this->displayForm();
  }

  /**
   * Builds the configuration form
   * @return string HTML code
   */
  public function displayForm()
  {
    $states = DiscontractModel::getInstance()->getOrderStates($this->context->language->id);
    // Init Fields form array
    $form = [
      'form' => [
        'legend' => [
          'title' => $this->l('Settings'),
        ],
        'input' => [
          [
            'type' => 'text',
            'label' => $this->l('Discontract API key'),
            'name' => 'DISCONTRACT_API_KEY',
            'size' => 20,
            'required' => true,
          ],
          [
            'type' => 'select',
            'label' => $this->l('Discontract environment'),
            'name' => 'DISCONTRACT_API_URL',
            'options' => array(
              'query' => array(
                array('name' => 'local', 'id_option' => 'http://localhost:8020/api/v1'),
                array('name' => 'staging', 'id_option' => 'https://b2b-stage.discontract.com/api/v1'),
                array('name' => 'production', 'id_option' => 'https://b2b.discontract.com/api/v1'),
              ),
              'name' => 'name',
              'id' => 'id_option',
            ),
            'size' => 3,
            'required' => true,
          ],
          [
            'type' => 'select',
            'label' => $this->l('Order purchased states'),
            'name' => 'DISCONTRACT_PURCHASED_STATES',
            'multiple' => true,
            'options' => array(
              'query' => $states,
              'name' => 'name',
              'id' => 'id_option',
            ),
            'size' => 5,
            'required' => true,
          ],
          [
            'type' => 'select',
            'label' => $this->l('Order delivered states'),
            'name' => 'DISCONTRACT_DELIVERED_STATES',
            'multiple' => true,
            'options' => array(
              'query' => $states,
              'name' => 'name',
              'id' => 'id_option',
            ),
            'size' => 5,
            'required' => true,
          ],
          [
            'type' => 'categories',
            'tree' => ['id' => 0, 'selected_categories' => array((int)Tools::getValue('DISCONTRACT_CATEGORY_ID', Configuration::get('DISCONTRACT_CATEGORY_ID')))],
            'label' => $this->l('Discontract Services Category'),
            'name' => 'DISCONTRACT_CATEGORY_ID',
            'size' => 20,
            'required' => true,
          ],
        ],
        'submit' => [
          'title' => $this->l('Save & Sync Jobs'),
          'class' => 'btn btn-default pull-right',
        ],
      ],
    ];

    $helper = new HelperForm();

    // Module, token and currentIndex
    $helper->table = $this->table;
    $helper->name_controller = $this->name;
    $helper->token = Tools::getAdminTokenLite('AdminModules');
    $helper->currentIndex = AdminController::$currentIndex . '&' . http_build_query(['configure' => $this->name]);
    $helper->submit_action = 'submit' . $this->name;
    // Default language
    $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
    // Load current value into the form
    $helper->fields_value['DISCONTRACT_API_URL'] = Configuration::get('DISCONTRACT_API_URL', null, null, null, 'http://localhost:8020/api/v1');
    $helper->fields_value['DISCONTRACT_API_KEY'] = Tools::getValue('DISCONTRACT_API_KEY', Configuration::get('DISCONTRACT_API_KEY'));
    // var_dump(Configuration::get('DISCONTRACT_PURCHASED_STATES'));
    $helper->fields_value['DISCONTRACT_PURCHASED_STATES[]'] = explode(',', Configuration::get('DISCONTRACT_PURCHASED_STATES'));
    $helper->fields_value['DISCONTRACT_DELIVERED_STATES[]'] = explode(',', Configuration::get('DISCONTRACT_DELIVERED_STATES'));
    $form1 = $helper->generateForm([$form]);

    if (Configuration::get('DISCONTRACT_API_KEY')) {
      $jobsGenerated = '';
      $jobs = DiscontractModel::getInstance()->getDiscontractJobs();
      for ($i = 0; $i < count($jobs); $i++) {
        $job = $jobs[$i];
        $jobsGenerated .= "<tr><td style='padding:5px'>".$job['title_lt']."</td><td td style='padding:5px'>".($job['price'] / 100)."€</td></tr>";
      }
      return $form1 . '<table border="1" style="background-color:white">'.$jobsGenerated.'</table>';
    } else {
      return $form1;
    }
  }

  public function install()
  {
    return parent::install()
      && $this->registerHook('actionAdminProductsControllerSaveAfter')
      && $this->registerHook('actionValidateCustomerAddressForm')
      && $this->registerHook('actionCartSave')
      && $this->registerHook('actionOrderStatusUpdate')
      && $this->registerHook('actionCartUpdateQuantityBefore')
      && $this->registerHook('displayAdminProductsMainStepLeftColumnMiddle')
      && $this->registerHook('displayDiscontractJobSelector')
      && $this->registerHook('displayCustomization')
      && $this->registerHook('actionFrontControllerSetMedia')
      && $this->registerHook('actionSetInvoice')
      && DiscontractModel::getInstance()->_installDb();
  }

  public function uninstall()
  {
    return parent::uninstall()
      && Configuration::deleteByName('DISCONTRACT_API_KEY')
      && Configuration::deleteByName('DISCONTRACT_CATEGORY_ID')
      && Configuration::deleteByName('DISCONTRACT_API_URL')
      && $this->unregisterHook('actionAdminProductsControllerSaveAfter')
      && $this->unregisterHook('actionCartUpdateQuantityBefore')
      && $this->unregisterHook('actionValidateCustomerAddressForm')
      && $this->unregisterHook('actionCartSave')
      && $this->unregisterHook('displayAdminProductsMainStepLeftColumnMiddle')
      && $this->unregisterHook('displayDiscontractJobSelector')
      && $this->unregisterHook('displayCustomization')
      && $this->unregisterHook('actionOrderStatusUpdate')
      && $this->unregisterHook('actionFrontControllerSetMedia')
      && $this->unregisterHook('actionSetInvoice')
      && DiscontractModel::getInstance()->_uninstallDb();
  }

  public function symfonyContainerInstance()
  {
    if (null != $this->symfonyInstance) {
      return $this->symfonyInstance;
    }

    $this->symfonyInstance = SymfonyContainer::getInstance();
    return $this->symfonyInstance;
  }

  private function getProductAdminHookFieldsDefinition($hookFieldsBuilder, array $data)
  {
    $formFactory = $this->symfonyContainerInstance()->get('form.factory');
    $options = [
      'csrf_protection' => false,
      'hookFieldsBuilder' => $hookFieldsBuilder,
      'module' => $this,
    ];

    return $formFactory->createNamed($this->name, Democustomfields17AdminForm::class, $data, $options);
  }

  public function hookActionValidateCustomerAddressForm($params) {
    $context = Context::getContext();
    $cart = $context->cart;
    $cartId = $cart->id;
    if ($cartId && DiscontractModel::getInstance()->getDiscontractCart($cartId)) {
      $form = $params['form'];
      $field = $form->getField('phone');
      $value = $field->getValue();
      $value = preg_replace("/[^0-9]/", "", $value);
      $value = preg_replace('/\s+/', '', $value);
      if (substr($value, 0, 1) == '8') {
        $value = '+370'.substr($value, 1);
      } else if (substr($value, 0, 3) == '370') {
        $value = '+'.$value;
      }
      $field->setValue($value);
      $response = DiscontractApi::getInstance()->validatePhoneNumber($value);
      if (property_exists($response, 'message') && $response->message == 'error.phoneNumber.invalid') {
        $field->addError($this->trans(
          'Klaidingas telefono numeris "%phone%". Įveskite pilną telefono numerį, kuris prasideda +370 arba 8',
          array('%phone%' => $value),
          'Shop.Forms.Errors'
        ));
      }
    }
  }

  public function hookActionFrontControllerSetMedia()
  {
      $this->context->controller->registerStylesheet(
          $this->name . '-style',
          'modules/'.$this->name.'/views/templates/hook/discontractpro.css',
          [
              'media'    => 'all',
              'priority' => 1000,
          ]
      );
      $this->context->controller->registerJavascript(
          $this->name,
          'modules/'.$this->name.'/views/templates/hook/discontractpro.js',
          [
              'position' => 'bottom',
              'priority' => 1000,
          ]
      );
  }

  public function hookActionOrderStatusUpdate($params)
  {
    $purchasedStates = explode(',', Configuration::get('DISCONTRACT_PURCHASED_STATES'));
    $deliveredStates = explode(',', Configuration::get('DISCONTRACT_DELIVERED_STATES'));
    $statusId = (int)$params['newOrderStatus']->id;
    $orderId  = (int)$params['id_order'];
    $data = DiscontractModel::getInstance()->getOrderInfo($orderId);
    $cartId  = (int)$data['id_cart'];
    $cart = DiscontractModel::getInstance()->getDiscontractCart($cartId);
    if (!$cart) {
      return;
    }

    if (in_array((string)$statusId, $purchasedStates)) { // car purchased
      $request = new stdClass();
      $request->billingDetails = new stdClass();
      $request->contactDetails = new stdClass();
      $request->comment = "";
      $request->billingDetails->firstName =  $data['firstname'];
      $request->billingDetails->lastName = $data['lastname'];
      $request->billingDetails->companyName = $data['company'];
      $request->billingDetails->businessCode = $data['dni'];
      $request->billingDetails->vatCode = $data['vat_number'];

      $request->contactDetails->firstName = $data['ad_firstname'];
      $request->contactDetails->lastName = $data['ad_lastname'];
      $request->contactDetails->phoneNumber = $data['ad_phone'];
      $request->contactDetails->email = $data['email'];
      if ($cart["status"] === 'reserved') {
        $response = DiscontractApi::getInstance()->purchaseCart($cart['id_discontract_cart'], $request);
        DiscontractModel::getInstance()->updateCartStatus($cartId, $response->status);
      }
    } else if (in_array((string)$statusId, $deliveredStates)) { // issiusta
      if ($cart["status"] === 'purchased') {
        $time = time() * 1000 + 3600 * 48 * 1000;
        $response = DiscontractApi::getInstance()->deliverCart($cart['id_discontract_cart'], $time);
        DiscontractModel::getInstance()->updateCartStatus($cartId, $response->status);
      }
    }
  }

  public function hookActionCartSave($params)
  {
    if (!isset($params['cart']) || $this->discontractCartRequestProcessed) {
      return;
    }
    $cartId = $params['cart']->id;
    $discontractCart = Tools::getValue('discontract_cart');
    if ($discontractCart) {
      // check if customization for same shopProductId already exists in DB, if yes, do not duplicate
      // or better yet get customization by placeID and increase product amount if necessary
      $qty = (int)Tools::getValue('qty');
      $this->discontractCartRequestProcessed = true;
      $discontractCart = json_decode($discontractCart);
      $value = json_encode($discontractCart);
      $customizationId = DiscontractModel::getInstance()->createDiscontractCustomization($cartId, $discontractCart->productId, 1, $value, ($discontractCart->price->arrivalCost / 100));
      DiscontractModel::getInstance()->addProductToCart($cartId, $discontractCart->productId, $discontractCart, $customizationId, $qty);
    }

    $customizations = DiscontractModel::getInstance()->getDiscontractCustomizationsForCart($cartId);
    if (count($customizations) === 0) {
      DiscontractModel::getInstance()->detachDiscontractCart($cartId);
      return;
    }
    $jobItems = array();
    for ($i = 0; $i < count($customizations); $i++) {
      $c = $customizations[$i];
      $customizationId = $c['id_customization'];
      $quantity = $c['quantity'];
      $job = json_decode($c['value']);
      $job->amount = (int)$quantity;
      $jobItems[] = $job;
      $job->externalItemId = $customizationId;
    }
    // get existing cart. if cart exists & quantities & address matches do not detach
    $api = DiscontractApi::getInstance();
    $currentCart = DiscontractModel::getInstance()->getDiscontractCart($cartId);
    if ($currentCart) {
      $resp = DiscontractApi::getInstance()->getCart($currentCart['id_discontract_cart']);
      $discontractCart = $resp->cart;
      if (count($discontractCart->items)  === count($jobItems)) {
        $cartsMatch = true;
        for ($i = 0; $i < count($jobItems); $i++) {
          $job = $jobItems[$i];
          $matchFound = false;
          for ($j = 0; $j < count($discontractCart->items); $j++) {
            $existingItem = $discontractCart->items[$j];
            if ($existingItem->externalItemId === $job->externalItemId && $job->amount === $existingItem->amount) {
              $matchFound = true;
              break;
            }
          }
          if (!$matchFound) {
            $cartsMatch = false;
            break;
          }
        }
        if ($cartsMatch) {
          // do no create new discontract cart
          return;
        }
      }
    }
    $response = $api->createCart($jobItems, $cartId);
    DiscontractModel::getInstance()->detachDiscontractCart($cartId);
    DiscontractModel::getInstance()->attachDiscontractCart($cartId, $response->cartId, $response->status);
    for ($i = 0; $i < count($response->items); $i++) {
      $order = $response->items[$i];
      // TODO: also update speicific prices in case there is an unexpected price change
      DiscontractModel::getInstance()->updateCustomizationPrice($order->externalItemId, ($order->price->arrivalCost / 100 / $order->amount));
    }
    return;
  }

  public function hookDisplayCustomization($params)
  {
    $value = json_decode($params['customization']['value']);
    return $value->location->description;
  }

  public function hookDisplayAdminProductsMainStepLeftColumnMiddle($params)
  {
    $job_id = DiscontractModel::getInstance()->getJobIdByProductId($params['id_product']);
    if (!DiscontractModel::getInstance()->isProductDiscontractService($params['id_product'])) {
      return "";
    }
    $productFieldsData = [
      'discontract_job_id' => $job_id
    ];
    $hookFieldsBuilder = new HookDisplayAdminProductsExtraFieldsBuilder();
    $form = $this->getProductAdminHookFieldsDefinition($hookFieldsBuilder, $productFieldsData);
    return $this->symfonyContainerInstance()
      ->get('twig')
      ->render('@PrestaShop/' . $this->name . '/admin/customfields.html.twig', [
        'form' => $form->createView(),
      ]);
  }

  public function hookActionSetInvoice($params) {
    $orderInvoice = $params["OrderInvoice"];
    DiscontractModel::getInstance()->removeDiscontractProductsFromInvoice($orderInvoice);
  }

  public function hookDisplayDiscontractJobSelector($params)
  {
    $productId = Tools::getValue('id_product');
    $resp = DiscontractModel::getInstance()->getJobIdByProductCategories($productId);

    // var_dump($resp);
    // die();
    $jobs = array();
    if (count($resp) > 0) {
      for ($i = 0; $i < count($resp); $i++) {
        $item = $resp[$i];
        $jobId = $item->jobId;
        // die($jobId);
        $job = DiscontractModel::getInstance()->getJob($jobId);
        $price = $job['price'];
        $currency = 'EUR';
        $titles = DiscontractModel::getInstance()->getProductTitles($item->productId);
        $job = array (
          'job_price' => number_format($price / 100, 2),
          'job_currency' => ' €',
          'job_id' => $jobId,
          'discontract_product_id' => $item->productId,
          'title' => $titles['name'],
          'description' => $titles['meta_description'],
        );
        if ($i === 0) {
          $job['selected'] = 'checked';
        } else {
          $job['selected'] = '';
        }
        $jobs[] = $job;
      }
      array_multisort(array_column($jobs, 'job_price'), $jobs);
      $titles = DiscontractModel::getInstance()->getProductTitles($productId);
      // TODO: title should come from product title
      $this->context->smarty->assign([
        'jobs' => $jobs,
        'product_id' => $productId,
        'product_name_real' => str_replace("'", "", $titles['name']),
        'ajax_params' => array('ajax' => true)
      ]);
      for ($i = 0; $i < count($jobs); $i++) {
        if ((int)$productId === (int)$job['discontract_product_id']) {
          return $this->display(__FILE__, 'disable.tpl');
        }
      }
      return $this->display(__FILE__, 'selector.tpl');
    }
  }

  public function hookActionAdminProductsControllerSaveAfter()
  {
    $dsc_params = Tools::getValue($this->name);
    $id_product = (int) Tools::getValue('id_product');
    if ($dsc_params['discontract_job_id'] != "") {
      DiscontractModel::getInstance()->setProductJobId($id_product, $dsc_params['discontract_job_id']);
    }
    // var_dump($params);
  }
}
