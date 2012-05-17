var sp_markersArray = [];
var sp_widgetmapArray = [];

function sp_propertybuildmap(id) {
	// Grab the id and data elements
	var useid = jQuery(this).attr('id');
	var usedata = useid.replace('spmap_', '');
	// Allow for nearby proeprties to be shown
	var neardata = useid.replace('spmap_', '') + '_near';

	var middle = new google.maps.LatLng(0.0,0.0);

	var myOptions = {
      zoom: 0,
      center: middle
    };

	var sp_propertywidgetmap = new google.maps.Map(document.getElementById(useid), myOptions);
	sp_widgetmapArray.push(sp_propertywidgetmap);

	// Set the map type
	if(jQuery('#' + useid).hasClass('ROADMAP')) { sp_propertywidgetmap.setMapTypeId( google.maps.MapTypeId.ROADMAP ); }
	if(jQuery('#' + useid).hasClass('SATELLITE')) { sp_propertywidgetmap.setMapTypeId( google.maps.MapTypeId.SATELLITE ); }
	if(jQuery('#' + useid).hasClass('HYBRID')) { sp_propertywidgetmap.setMapTypeId( google.maps.MapTypeId.HYBRID ); }
	if(jQuery('#' + useid).hasClass('TERRAIN')) { sp_propertywidgetmap.setMapTypeId( google.maps.MapTypeId.TERRAIN ); }

	//if(jQuery('#' + useid).hasClass('minimumzoom')) {
	//	for(zoom = 0; zoom <= 18; zoom++) {
	//		if(jQuery('#' + useid).hasClass('minimumzoom-' + zoom) ) { sp_propertywidgetmap.minZoom = zoom; }
	//	}
	//}

	if(typeof staypress_data[usedata] != 'undefined') {

		var llb = new google.maps.LatLngBounds();

		var markercounter = 0;
		jQuery.each(staypress_data[usedata], function(key, value) {

			if(value.latitude != '' && value.longitude != '') {

				var markerlatlng = new google.maps.LatLng(value.latitude, value.longitude)
				llb.extend( markerlatlng );

				var image = new google.maps.MarkerImage(staypress_data.mapicon.replace('%%', key),
					new google.maps.Size(27, 27),
					new google.maps.Point(0,0),
					new google.maps.Point(13, 27));
				var shadow = new google.maps.MarkerImage(staypress_data.mapshadow,
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
						map: sp_propertywidgetmap,
						shadow: shadow,
						icon: image,
						title: "Icon"
					  });
				}

				sp_markersArray[markercounter] = marker;

				google.maps.event.addListener(marker, 'click', function() {
					//console.log(sp_markersArray[markercounter]);
					sp_clickMarker(marker, sp_propertywidgetmap);
				});

				markercounter++;

			}

		});

		if(typeof staypress_data[neardata] != 'undefined') {
			jQuery.each(staypress_data[neardata], function(key, value) {

				if(value.latitude != '' && value.longitude != '') {

					var markerlatlng = new google.maps.LatLng(value.latitude, value.longitude)
					llb.extend( markerlatlng );

					var image = new google.maps.MarkerImage(staypress_data.nearicon.replace('%%', key),
						new google.maps.Size(27, 27),
						new google.maps.Point(0,0),
						new google.maps.Point(13, 27));
					var shadow = new google.maps.MarkerImage(staypress_data.nearshadow,
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
							map: sp_propertywidgetmap,
							shadow: shadow,
							icon: image,
							title: "Icon"
						  });
					}

					sp_markersArray[markercounter] = marker;

					google.maps.event.addListener(marker, 'click', function() {
						//console.log(sp_markersArray[markercounter]);
						sp_clickMarker(marker, sp_propertywidgetmap);
					});

					markercounter++;

				}

			});
		}

		if(!llb.isEmpty()) {
			sp_propertywidgetmap.fitBounds(llb);

			if(jQuery('#' + useid).hasClass('clustermarkers')) {
				var mcOptions = {gridSize: 30};
				var markerCluster = new MarkerClusterer(sp_propertywidgetmap, sp_markersArray, mcOptions);
			}
		}

		// To check for obfuscation of pointers
		google.maps.event.addListener(sp_propertywidgetmap, 'zoom_changed', function() {
			sp_zoomwidgetmap(sp_propertywidgetmap);
		});

	}
}

function sp_zoomwidgetmap(map) {
	//code in here to handle obfuscation of markers
}

function sp_clickMarker(marker, map) {
	map.setCenter( marker.getPosition() );
	map.setZoom(14);
}

function sp_propertywidgetready() {
	jQuery('.spmap').each( sp_propertybuildmap );
}

jQuery(document).ready(sp_propertywidgetready);