/**
 * 2006-2021 THECON SRL
 *
 * NOTICE OF LICENSE
 *
 * DISCLAIMER
 *
 * YOU ARE NOT ALLOWED TO REDISTRIBUTE OR RESELL THIS FILE OR ANY OTHER FILE
 * USED BY THIS MODULE.
 *
 * @author    THECON SRL <contact@thecon.ro>
 * @copyright 2006-2021 THECON SRL
 * @license   Commercial
*/

$(document).ready(function() {
    if (tha_vat_number) {
        $('body').on('focusout', tha_vat_number, function() {
            let tha_vat = $(this).val();
            if (tha_vat) {
                let $tha_form = $(this).closest('form');
                $.ajax({
                    url: tha_ajax_url,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        ajax: true,
                        action: 'getCompanyDetails',
                        vat_number: tha_vat
                    },
                    success: function (r) {
                        if (r.error) {
                            console.log(r.result);
                        } else {
                            if (tha_company && r.result.company) {
                                $tha_form.find(tha_company).val(r.result.company);
                            }

                            if (tha_address && r.result.address) {
                                $tha_form.find(tha_address).val(r.result.address);
                            }

                            if (tha_city && r.result.city) {
                                $tha_form.find(tha_city).val(r.result.city).trigger('change');
                            }

                            if (tha_phone && r.result.phone) {
                                $tha_form.find(tha_phone).val(r.result.phone);
                            }

                            if (tha_postcode && r.result.postcode) {
                                $tha_form.find(tha_postcode).val(r.result.postcode).trigger('change');
                            }

                            if (tha_dni && r.result.reg_com) {
                                $tha_form.find(tha_dni).val(r.result.reg_com);
                            }

                            if (tha_state && r.result.state) {
                                $tha_form.find(tha_state + " option").filter(function() {
                                    return $(this).text() == r.result.state;
                                }).prop('selected', true).trigger('change');
                            }
                        }
                    }
                });
            }
        });
    }
});
