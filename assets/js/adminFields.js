jQuery(document).ready(function ($) {
    
    if($('#coinsnap_provider').length){
        
        setProvider();
        $('#coinsnap_provider').change(function(){
            setProvider();
        });
    }
    
    function setProvider(){
        if($('#coinsnap_provider').val() === 'coinsnap'){
            $('tr.gateway_btcpay').hide();
            $('tr.gateway_btcpay input[type=text]').removeAttr('required');
            $('tr.gateway_coinsnap').show();
            $('tr.gateway_coinsnap input[type=text]').attr('required','required');
        }
        else {
            $('tr.gateway_coinsnap').hide();
            $('tr.gateway_coinsnap input[type=text]').removeAttr('required');
            $('tr.gateway_btcpay').show();
            $('tr.gateway_btcpay input[type=text]').attr('required','required');
        }
    }
    
    function isValidPMProUrl(serverUrl) {
        try {
            const url = new URL(serverUrl);
            if (url.protocol !== 'https:' && url.protocol !== 'http:') {
                return false;
            }
	}
        catch (e) {
            console.error(e);
            return false;
	}
        return true;
    }

    $('.pmpro-btcpay-apikey-link').click(function(e) {
        e.preventDefault();
        const host = $('#btcpay_server_url').val();
	if (isValidPMProUrl(host)) {
            let data = {
                'action': 'pmpro_btcpay_server_apiurl_handler',
                'host': host,
                'apiNonce': coinsnappmpro_ajax.nonce
            };
            
            $.post(coinsnappmpro_ajax.ajax_url, data, function(response) {
                if (response.data.url) {
                    window.location = response.data.url;
		}
            }).fail( function() {
		alert('Error processing your request. Please make sure to enter a valid BTCPay Server instance URL.')
            });
	}
        else {
            alert('Please enter a valid url including https:// in the BTCPay Server URL input field.')
        }
    });
});

