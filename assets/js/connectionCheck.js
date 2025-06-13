jQuery(function ($) {
    
    console.log('Connection check is activated');
    
    let connectionCheckElement = '';
    
    if($('hr.wp-header-end').length){
        connectionCheckElement = 'hr.wp-header-end';
    }
    
    if(connectionCheckElement !== ''){
    
        let ajaxurl = coinsnappmpro_ajax['ajax_url'];
        let data = {
            action: 'coinsnap_connection_handler',
            _wpnonce: coinsnappmpro_ajax['nonce']
        };

        jQuery.post( ajaxurl, data, function( response ){

            connectionCheckResponse = $.parseJSON(response);
            let resultClass = (connectionCheckResponse.result === true)? 'success' : 'error';
            $connectionCheckMessage = '<div id="coinsnapConnectionTopStatus" class="message '+resultClass+' notice" style="margin-top: 10px;"><p>'+ connectionCheckResponse.message +'</p></div>';

            $(connectionCheckElement).after($connectionCheckMessage);

            if($('.coinsnapConnectionStatus').length){
                $('.coinsnapConnectionStatus').html('<span class="'+resultClass+'">'+ connectionCheckResponse.message +'</span>');
            }
        });
    }
    
    function getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
    }

    function setCookie(name, value, days) {
        const expDate = new Date(Date.now() + days * 86400000);
        const expires = "expires=" + expDate.toUTCString();
        document.cookie = name + "=" + value + ";" + expires + ";path=/";
    }
});

