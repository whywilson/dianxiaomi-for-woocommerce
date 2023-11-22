var dianxiaomi_woocommerce_tracking_onload_run = false;

var dianxiaomi_woocommerce_tracking_onload = function () {
	if (dianxiaomi_woocommerce_tracking_onload_run) {
		return dianxiaomi_woocommerce_tracking_onload_run;
	}
	dianxiaomi_woocommerce_tracking_onload_run = true;

	var fields_id = {
		'tracking_ship_date': 'dianxiaomi_tracking_shipdate',
		'tracking_postal_code': 'dianxiaomi_tracking_postal',
		'tracking_account_number': 'dianxiaomi_tracking_account',
		'tracking_key': 'dianxiaomi_tracking_key',
		'tracking_destination_country': 'dianxiaomi_tracking_destination_country'
	};

	var providers;

	function hide_input_and_label(id) {
		jQuery('#' + id).hide();
		jQuery('label[for=' + id + ']').hide();
	}

	function show_input_and_label(id) {
		jQuery('#' + id).show();
		jQuery('label[for=' + id + ']').show();
	}

	function set_dianxiaomi_tracking_provider() {

		jQuery('#dianxiaomi_tracking_provider').change(function () {
			jQuery.each(fields_id, function (index, item) {
				hide_input_and_label(item);
			});

			var slug = jQuery(this).val();
			if (slug) {
				var provider = providers[slug];
				var fields = [];
				if (jQuery.isArray(provider.required_fields)) {
					fields = provider.required_fields;
				} else {
					fields.push(provider.required_fields);
				}
				jQuery.each(fields, function (index, item) {
					if (fields_id[item]) {
						show_input_and_label(fields_id[item]);
					}
				});
				jQuery('#dianxiaomi_tracking_provider_name').val(provider.name);
				jQuery('#dianxiaomi_tracking_required_fields').val(fields.join());
			}
		});
	}


	function fill_meta_box(couriers_selected) {
		var response = get_couriers();
		var couriers = [];
		jQuery.each(response, function (index, courier) {
			if (couriers_selected.indexOf(courier.slug) != -1) {
				couriers.push(courier);
			}
		});

		var selected_provider = jQuery('#dianxiaomi_tracking_provider_hidden').val();
		var find_selected_provider = couriers_selected.indexOf(selected_provider) != -1;
		if (!find_selected_provider && selected_provider) {
			couriers.push({
				slug: selected_provider,
				name: jQuery("#dianxiaomi_tracking_provider_name").val(),
				required_fields: jQuery("#dianxiaomi_tracking_required_fields").val()
			});
		}
//		console.log(couriers);

		couriers = sort_couriers(couriers);

		jQuery.each(couriers, function (key, courier) {
			var str = '<option ';
			if (!find_selected_provider && courier['slug'] == selected_provider) {
				str += 'style="display:none;" ';
			}
			str += 'value="' + courier['slug'] + '" ';
			if (courier['slug'] == selected_provider) {
				str += 'selected="selected"';
			}
			str += '>' + courier['name'] + '</option>';
			jQuery('#dianxiaomi_tracking_provider').append(str);
		});
//		jQuery('#dianxiaomi_tracking_provider').val(selected_provider);
		jQuery('#dianxiaomi_tracking_provider').trigger("chosen:updated");
		jQuery('#dianxiaomi_tracking_provider_chosen').css({width: '100%'});

		providers = {};
		jQuery.each(couriers, function (index, courier) {
			providers[courier.slug] = courier;
		});
		set_dianxiaomi_tracking_provider();
		jQuery('#dianxiaomi_tracking_provider').trigger('change');
	}

	if (jQuery('#dianxiaomi_tracking_provider').length > 0) {

		jQuery.each(fields_id, function (index, item) {
			hide_input_and_label(item);
		});

		var couriers_selected = jQuery('#dianxiaomi_couriers_selected').val();
		var couriers_selected_arr = (couriers_selected) ? couriers_selected.split(',') : [];
		fill_meta_box(couriers_selected_arr);
	}

	if (jQuery('#dianxiaomi_tracking_provider_name').length > 0) {
		jQuery('#dianxiaomi_tracking_provider_name').parent().hide();
	}

	if (jQuery('#dianxiaomi_tracking_required_fields').length > 0) {
		jQuery('#dianxiaomi_tracking_required_fields').parent().hide();
	}

	return dianxiaomi_woocommerce_tracking_onload_run;
};
