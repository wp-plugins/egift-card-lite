function wpgc_ecard(prefix) {
	if (jQuery("#"+prefix+"ecard").is(":checked")) {
		jQuery("#"+prefix+"ecards").slideDown(300);
	} else {
		jQuery("#"+prefix+"ecards").slideUp(300);
	}
}
