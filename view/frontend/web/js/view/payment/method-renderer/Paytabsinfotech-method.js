/*browser:true*/
/*global define*/
define(
        [
            'Magento_Checkout/js/view/payment/default',
            'mage/url'
        ],
        function (Component, url) {
            'use strict';

            return Component.extend({
                defaults: {
                    template: 'Ambientinfotech_Paytabsinfotech/payment/Paytabsinfotech-form'
                },
                redirectAfterPlaceOrder: false,
                /**
                 * After place order callback
                 */
                afterPlaceOrder: function () {
                    //window.location.replace(url.build('citrus/checkout/start'));
                    jQuery(function ($) {
                        //alert(url.build('citrus/checkout/start'));
                        //$("#Ambientinfotechloader",parent.document).html('<b>OK</b>');

                        $.ajax({
                            url: url.build('Paytabsinfotech/checkout/start'),
                            type: 'get',
                            dataType: 'json',
                            cache: false,
                            processData: false, // Don't process the files
                            contentType: false, // Set content type to false as jQuery will tell the server its a query string request
                            success: function (data) {
                                $("#AmbientinfotechPaytabsinfotechloader", parent.document).html(data['html']);
                                //alert(data['html']);
                                $('body').trigger('processStop');
                            },
                            error: function (xhr, ajaxOptions, thrownError) {
                                alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
                            }
                        });
                    });
                }
            });
        }
);
