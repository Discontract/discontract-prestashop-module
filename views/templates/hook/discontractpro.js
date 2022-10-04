const DISCONTRACT_MODULE = function() {
  function callOnce(func, within=500, timerId=null){
    window.callOnceTimers = window.callOnceTimers || {};
    if (timerId == null) 
        timerId = func;
    var timer = window.callOnceTimers[timerId];
    clearTimeout(timer);
    timer = setTimeout(() => func(), within);
    window.callOnceTimers[timerId] = timer;
  }
  function discontractPerformRequest(action, params, callback) {
    const url = DISCONTRACT_MODULE_LINK;
    params.action = action;
    params.ajax = true;
    $.ajax({
      type: 'POST',
      dataType : 'JSON',
      url: url,
      cache: false,
      data: params,
      success: callback
    });
  }
  function discontractAutocompleteAddress(postCode) {
    const params = {};
    params.postCode = postCode;
    discontractPerformRequest('locations', params, function(result) {
      //console.log(result);
      const $autocomplete = document.getElementById('disc-postcode-autocomplete');
      if (result.length) {
        document.getElementById('disc-postcode-autocomplete').style.display = 'block';
      }
      document.getElementById('disc-postcode-autocomplete').innerHTML="";
      document.getElementById('discontract-loader').style.display = 'none';
      result.forEach(item => {
        const $node = document.createElement('li');
        $node.style.padding = '13px';
        $node.style['padding-top'] = '6px';
        $node.style['padding-bottom'] = '6px';
        $node.style['border-bottom'] = '1px solid #E3E3E3';
        $node.style.cursor = 'pointer';
        $node.appendChild(document.createTextNode(item.postCode + ' - ' + item.description));
        $node.addEventListener('click', (e) => {
          e.stopPropagation();
          document.getElementById('discontract-loader').style.display = 'block';
          $autocomplete.innerHTML = "";
          //console.log(item.description, item.lat, item.lng);
          item.productId = DISCONTRACT_PRODUCT_ID;
          item.jobId = $("input[name=id_discontract_job]:checked").val();;
          document.getElementById('disc-postcode-autocomplete').style.display = 'none';
          discontractPerformRequest('getPrice', item, function(result) {
            result.shopProductId = DISCONTRACT_PRODUCT_ID;
            result.productName = DISCONTRACT_PRODUCT_NAME;
            const $priceBlock = $("input[name=id_discontract_job]:checked").next();
            result.productId = $priceBlock.find('.discontract_product_id').val();
            document.getElementById('discontract_cart').value = JSON.stringify(result);
            $('button.add-to-cart').prop('disabled', false);
            $priceBlock.css('display', 'inline');
            $priceBlock.find('.discontract-price').text((result.price.total / 100).toFixed(2) + ' €');
            document.getElementById('disc-postcode-input').value = item.postCode + ' - ' + item.description;
            document.getElementById('discontract-loader').style.display = 'none';
          });
          return false;
        });
        $autocomplete.appendChild($node);
      });
    });
  }

  document.getElementById('disc-postcode-input').addEventListener('keyup', function(event) {
    callOnce(function() {
      $('button.add-to-cart').prop('disabled', true);
      const value = event.target.value.trim();
      if (value.length > 1) {
        document.getElementById('discontract-loader').style.display = 'block';
        discontractAutocompleteAddress(value);
      }
    });
  });
  document.getElementById('disc-postcode-input').addEventListener('focus', function(event) {
    $('button.add-to-cart').prop('disabled', true);
    document.getElementById('disc-postcode-input').value = '';
    document.getElementById('discontract_cart').value = '';
  });
  document.querySelectorAll("input[name='id_discontract_job']").forEach((input) => {
    input.addEventListener('change', function() {
      $('.disc-price-block').css('display', 'none');
      $('button.add-to-cart').prop('disabled', true);
      document.getElementById('disc-postcode-input').value = '';
      document.getElementById('discontract_cart').value = '';
    });
  });
  const checkbox = document.getElementById('discontract-job-check');
  checkbox.addEventListener('change', (event) => {
    if (event.currentTarget.checked) {
      $('.disc-price-block').css('display', 'none');
      document.getElementById('disc-postcode-block').style.display = 'block';
      if ($('.discontract-job').length > 1) {
        $('.disc-radio-choice').css('display', 'inline');
      }

      document.getElementById('disc-explanation-block').style.display = 'none';
      document.getElementById('disc-postcode-autocomplete').style.display = 'none';
      $('.discontract-asterisk').css('display', 'none');
      document.getElementById('disc-postcode-input').focus();
      $('button.add-to-cart').prop('disabled', true);
    } else {
      $('.disc-price-block').css('display', 'inline');
      $('.disc-radio-choice').css('display', 'none');
      document.getElementById('disc-postcode-block').style.display = 'none';
      document.getElementById('disc-explanation-block').style.display = 'block';
      document.getElementById('discontract_cart').value = '';
      $('.discontract-asterisk').css('display', 'inline');
      $('button.add-to-cart').prop('disabled', false);
    }
  });
  checkbox.checked = false;
  document.addEventListener('click', function() {
    document.getElementById('disc-postcode-autocomplete').style.display = 'none';
    document.getElementById('discontract-loader').style.display = 'none';
  });
};

if (typeof DISCONTRACT_MODULE_LINK !== 'undefined') {
  DISCONTRACT_MODULE();
}