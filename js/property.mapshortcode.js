var sp_shortcodemarkersArray = [];
var sp_shortcodemapArray = [];

function sp_propertyshortcodebuildmap() {
	// Grab the id and data elements
	var useid = jQuery(this).attr('id');
	var usedata = useid.replace('staypress_map_', '');

	var middle = new google.maps.LatLng(0.0,0.0);

	var myOptions = {
      zoom: 0,
      center: middle,
      mapTypeId: google.maps.MapTypeId.ROADMAP
    };

	var sp_propertyshortcodemap = new google.maps.Map(document.getElementById(useid), myOptions);

	if(typeof staypress_shortcode_data['property_' + usedata] != 'undefined') {

		var llb = new google.maps.LatLngBounds();

		var markercounter = 0;
		jQuery.each(staypress_shortcode_data['property_' + usedata], function(key, value) {

			if(value.latitude != '' && value.longitude != '') {

				var markerlatlng = new google.maps.LatLng(value.latitude, value.longitude)
				llb.extend( markerlatlng );

				var image = new google.maps.MarkerImage(staypress_shortcode_data['mapicon_' + usedata].replace('%%', key),
					new google.maps.Size(27, 27),
					new google.maps.Point(0,0),
					new google.maps.Point(13, 27));
				var shadow = new google.maps.MarkerImage(staypress_shortcode_data['mapshadow_' + usedata],
					new google.maps.Size(51, 37),
					new google.maps.Point(0,0),
					new google.maps.Point(13, 37));

				if(jQuery('#' + useid).hasClass('clustermarkers')) {
					var marker = new google.maps.Marker({
					    position: markerlatlng,
						shadow: shadow,
						icon: image,
						title: "Icon"
					  });
				} else {
					var marker = new google.maps.Marker({
					    position: markerlatlng,
						map: sp_propertyshortcodemap,
						shadow: shadow,
						icon: image,
						title: "Icon"
					  });
				}

				sp_shortcodemarkersArray[markercounter] = marker;

				google.maps.event.addListener(marker, 'click', function() {
					//console.log(sp_markersArray[markercounter]);
					sp_clickMarker(marker, sp_propertyshortcodemap);
				});

				markercounter++;

			}

		});

		if(typeof staypress_shortcode_data['near_' + usedata] != 'undefined') {
			jQuery.each(staypress_shortcode_data['near_' + usedata], function(key, value) {

				if(value.latitude != '' && value.longitude != '' && value.ID != usedata) {

					var markerlatlng = new google.maps.LatLng(value.latitude, value.longitude)
					llb.extend( markerlatlng );

					var image = new google.maps.MarkerImage(staypress_shortcode_data['nearicon_' + usedata].replace('%%', key),
						new google.maps.Size(27, 27),
						new google.maps.Point(0,0),
						new google.maps.Point(13, 27));
					var shadow = new google.maps.MarkerImage(staypress_shortcode_data['nearshadow_' + usedata],
						new google.maps.Size(51, 37),
						new google.maps.Point(0,0),
						new google.maps.Point(13, 37));

					if(jQuery('#' + useid).hasClass('clustermarkers')) {
						var marker = new google.maps.Marker({
						    position: markerlatlng,
							shadow: shadow,
							icon: image,
							title: "Icon"
						  });
					} else {
						var marker = new google.maps.Marker({
						    position: markerlatlng,
							map: sp_propertyshortcodemap,
							shadow: shadow,
							icon: image,
							title: "Icon"
						  });
					}

					sp_shortcodemarkersArray[markercounter] = marker;

					google.maps.event.addListener(marker, 'click', function() {
						//console.log(sp_markersArray[markercounter]);
						//sp_clickMarker(marker, sp_propertyshortcodemap);
					});

					markercounter++;

				}

			});
		}

		if(!llb.isEmpty()) {
			sp_propertyshortcodemap.fitBounds(llb);

			if(jQuery('#' + useid).hasClass('clustermarkers')) {
				var mcOptions = {gridSize: 30};
				var markerCluster = new MarkerClusterer(sp_propertyshortcodemap, sp_shortcodemarkersArray, mcOptions);
			}
		}

		// To check for obfuscation of pointers
		//google.maps.event.addListener(sp_propertyshortcodemap, 'zoom_changed', function() {
		//	sp_zoomwidgetmap(sp_propertyshortcodemap);
		//});
	}
}

function sp_propertyshortcodeready() {
	jQuery('.staypress_map').each( sp_propertyshortcodebuildmap );
}

jQuery(document).ready(sp_propertyshortcodeready);