<?php

require_once(dirname(__FILE__) . '/../../classes/DiscontractApi.php');
require_once(dirname(__FILE__) . '/../../classes/DiscontractModel.php');

class DiscontractproDiscontractAjaxModuleFrontController extends ModuleFrontController
{
  public function initContent()
  {
    parent::initContent();
    $this->ajax = true;
  }

  public function postProcess()
  {
    $action = Tools::getValue('action');
    $api = DiscontractApi::getInstance();
    if ($action === 'locations') {
      $locations = $api->getLocations(Tools::getValue('postCode'));
      die(json_encode($locations));
      // die('[{"postCode":"11329","lat":"54.651886","lng":"25.348360","description":"Airi\u0173 g. 1, Vilnius"},{"postCode":"11329","lat":"54.652248","lng":"25.348738","description":"Airi\u0173 g. 2, Vilnius"},{"postCode":"11329","lat":"54.651775","lng":"25.348730","description":"Airi\u0173 g. 3, Vilnius"},{"postCode":"11329","lat":"54.652195","lng":"25.349113","description":"Airi\u0173 g. 4, Vilnius"},{"postCode":"11329","lat":"54.651669","lng":"25.349117","description":"Airi\u0173 g. 5, Vilnius"},{"postCode":"11329","lat":"54.652145","lng":"25.349464","description":"Airi\u0173 g. 6, Vilnius"},{"postCode":"11329","lat":"54.652550","lng":"25.348812","description":"Angl\u0173 g. 1, Vilnius"},{"postCode":"11329","lat":"54.653004","lng":"25.348902","description":"Angl\u0173 g. 2, Vilnius"},{"postCode":"11329","lat":"54.652431","lng":"25.349342","description":"Angl\u0173 g. 3, Vilnius"},{"postCode":"11329","lat":"54.653214","lng":"25.349165","description":"Angl\u0173 g. 4, Vilnius"}]');
    } else if ($action === 'getPrice') {
      $address = new stdClass();
      $address->lat = (float)Tools::getValue('lat');
      $address->lng = (float)Tools::getValue('lng');
      $address->description = Tools::getValue('description');
      $response = $api->getPriceQuote(Tools::getValue('jobId'), array("location" => $address));
      $response->jobId = Tools::getValue('jobId');
      $response->location = $address;
      die(json_encode($response));
    } else {
      die('{"status":"error"}');
    }
  }
}
