// Check for a new version of the Ushahidi Software
jQuery(document).ready(function() {

	// Prevent an HTTP call if auto upgrading isn't enabled
	<?php if (Kohana::config('config.enable_auto_upgrader') == TRUE): ?>

	// Check if we need to upgrade this deployment of Ushahidi
	// if we're on the dashbboard, check for a new version
	jQuery.get("<?php echo url::base().'admin/upgrader/check_current_version' ?>", function(data){
			jQuery('#to_upgrade').html(data);
			jQuery('#to_upgrade').removeAttr("style");
		});

	<?php endif; ?>
		
});
