var i=0;
var backup = <?php echo ($js_data['backup']) ? 1 : 0; ?>;
$(document).ready(function() {
	upgrade_error = false;
	for (i=0; i < 10; i++) {
		if (backup == 0 && i == 5){
			i = i + 1;
		} else if (backup == 1 && i == 6) {
			i = i + 1;
		}
		$.ajax({
			url: "<?php echo Url::base()."status.php?step=";?>"+i,
			async: false,
			dataType: "json",
			success: function(data) {
				if (data.status == 'success'){
					$('#upgrade_log').append("<div class=\"upgrade_log_message log_success\">"+data.message+"</div>");
				} else if (data.status == 'error') {
					$('#upgrade_log').append("<div class=\"upgrade_log_message log_error\">"+data.message+" <a href=\"<?php echo $js_data['log_file']; ?>\" target=\"_blank\"><?php echo 'Log File' ?></a></div>");
					upgrade_error = TRUE;
				} else {
					upgrade_error = TRUE;
				}
			},
			error: function(data) {
				upgrade_error = TRUE;
			}
		});
			
		if (upgrade_error) {
			break;
		}
	}
});