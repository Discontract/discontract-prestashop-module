<!-- Block discontractpro -->
<div id="discontractpro_block_home" class="block" style="margin-top: 10px">
  <div style="display:flex;justify-items:align-center">
    <input style="margin-right:12px;margin-bottom: 5px" value="yes" type="checkbox" id="discontract-job-check" name="discontract_job_enabled"/>
    <h4 style="font-size: 18px;font-weight:600">{l s='Meistro paslauga' mod='discontractpro'}</h4>
  </div>
  <div>
    <input type="hidden" id="discontract_cart" name="discontract_cart" />
    {foreach from=$jobs item=job}
      <div style="font-size:18px;font-weight:500;margin-top:5px">
        <input type="radio" class="disc-radio-choice" {$job.selected} name="id_discontract_job" value="{$job.job_id}" style="display:none">
        <span class="disc-price-block">
          <input type="hidden" class="discontract_product_id" name="discontract_product_id" value="{$job.discontract_product_id}" />
          <span style="color:#00528E;font-weight:600" class="discontract-price">{$job.job_price}{$job.job_currency}</span><span class="discontract-asterisk">*</span> - </span>
        {$job.title}
      </div>
    {/foreach}
    <div style="font-size:14px;margin-bottom: 10px; margin-top:10px;" id="disc-explanation-block">*kaina gali kisti nuo pasirinkto atvykimo adreso</div>
    <div style="position:relative;display:none;margin-top: 10px" id="disc-postcode-block">
      <div style="position: relative">
        <input autocomplete="off" id="disc-postcode-input" style="max-width:430px;width:100%;display:block;padding:13px;font-size:14px;border:1px solid #E3E3E3;outline: none;" placeholder="Įveskite adresą arba pašto kodą kainos apskaičiavimui" type="text" name="post_code" />
        <i id="discontract-loader" style='display:none;position:absolute;right: 50px;top:17px' class="fa fa-circle-o-notch fa-spin fa-fw spinner-icon"></i>
      </div>
      <ul id="disc-postcode-autocomplete" style="z-index:9999;border: 1px solid #E3E3E3; position: absolute; width: 100%; max-width: 430px; background-color: white; padding 5px;">
      </ul>
    </div>
  </div>
  <script type="text/javascript">
  (function() {
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
      const url = "{$link->getModuleLink('discontractpro', 'discontractAjax', $ajax_params)}";
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
            item.productId = {$product_id};
            item.jobId = $("input[name=id_discontract_job]:checked").val();;
            document.getElementById('disc-postcode-autocomplete').style.display = 'none';
            discontractPerformRequest('getPrice', item, function(result) {
              result.shopProductId = {$product_id};
              result.productName = '{$product_name_real}';
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
    const disc_api_url = "{$link->getModuleLink('discontractpro', 'discontractAjax', $ajax_params)}";
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
        {if $jobs|@count gt 1}
          $('.disc-radio-choice').css('display', 'inline');
        {/if}

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
  })();
  </script>
</div>
<!-- /Block discontractpro -->